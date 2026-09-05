<?php

declare(strict_types=1);

use App\Modules\Admin\User\Controllers\AuthorizationFixtureController;
use App\Modules\Admin\User\Policies\AuthorizationFixturePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravarc\Core\Authorization\CompiledAuthorizationRegistry;
use Laravarc\Core\Authorization\ControllerModuleResolver;
use Laravarc\Core\Authorization\GatePolicyEvaluator;
use Laravarc\Core\Authorization\PolicyTargetResolver;
use Laravarc\Core\Metadata\MetadataArtifact;
use Laravarc\Core\Tests\Fixtures\FakeMetadataReader;
use Laravarc\Core\Tests\Fixtures\Models\AuthorizationFixtureModel;

describe('ControllerModuleResolver', function () {
    it('derives module key from controller class namespace', function () {
        $resolver = new ControllerModuleResolver(
            modulesPath: config('laravarc.modules_path'),
            moduleNamespace: 'App\\Modules',
            moduleKeyResolver: app(\Laravarc\Core\Contracts\ModuleKeyResolver::class),
        );

        expect($resolver->resolveFromClass(AuthorizationFixtureController::class))
            ->toBe('admin.user');
    });
});

describe('GatePolicyEvaluator', function () {
    it('authorizes index routes via viewAny using compiled metadata', function () {
        Gate::policy(AuthorizationFixtureModel::class, AuthorizationFixturePolicy::class);

        $artifact = authorizationArtifact();
        $evaluator = authorizationEvaluatorWithArtifact($artifact);

        Route::get('/users', [AuthorizationFixtureController::class, 'index']);

        $request = \Illuminate\Http\Request::create('/users', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        expect(fn () => $evaluator->authorize($request))->not->toThrow(AuthorizationException::class);
    });

    it('requires all repeatable policy attributes to pass', function () {
        Gate::policy(AuthorizationFixtureModel::class, AuthorizationFixtureDenyPolicy::class);

        $artifact = new MetadataArtifact(
            modules: [
                'admin.user' => authorizationModulePayload(
                    controllers: [
                        AuthorizationFixtureController::class => [
                            'model' => null,
                            'policy' => null,
                            'public' => false,
                            'methods' => [
                                'index' => [
                                    'requirements' => [
                                        ['abilities' => ['viewAny'], 'model' => null],
                                        ['abilities' => ['create'], 'model' => null],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ),
            ],
        );

        $evaluator = authorizationEvaluatorWithArtifact($artifact);

        Route::get('/users-deny', [AuthorizationFixtureController::class, 'index']);

        $request = \Illuminate\Http\Request::create('/users-deny', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        expect(fn () => $evaluator->authorize($request))
            ->toThrow(AuthorizationException::class);
    });

    it('accepts any ability listed in a single requirement', function () {
        Gate::policy(AuthorizationFixtureModel::class, AuthorizationFixturePolicy::class);

        $artifact = new MetadataArtifact(
            modules: [
                'admin.user' => authorizationModulePayload(
                    controllers: [
                        AuthorizationFixtureController::class => [
                            'model' => null,
                            'policy' => null,
                            'public' => false,
                            'methods' => [
                                'index' => [
                                    'requirements' => [
                                        ['abilities' => ['create', 'viewAny'], 'model' => null],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ),
            ],
        );

        $evaluator = authorizationEvaluatorWithArtifact($artifact);

        Route::get('/users-any', [AuthorizationFixtureController::class, 'index']);

        $request = \Illuminate\Http\Request::create('/users-any', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        expect(fn () => $evaluator->authorize($request))->not->toThrow(AuthorizationException::class);
    });

    it('denies authorization when target cannot be resolved', function () {
        $artifact = MetadataArtifact::empty();
        $evaluator = authorizationEvaluatorWithArtifact($artifact);

        Route::get('/deny-users', [AuthorizationFixtureController::class, 'index']);

        $request = \Illuminate\Http\Request::create('/deny-users', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        expect(fn () => $evaluator->authorize($request))
            ->toThrow(AuthorizationException::class);
    });

    it('allows public controller methods when require_policy is enabled', function () {
        config(['laravarc.require_policy' => true]);

        $artifact = new MetadataArtifact(
            modules: [
                'admin.user' => authorizationModulePayload(
                    controllers: [
                        AuthorizationFixtureController::class => [
                            'model' => null,
                            'policy' => null,
                            'public' => true,
                            'methods' => [],
                        ],
                    ],
                ),
            ],
        );

        $evaluator = authorizationEvaluatorWithArtifact($artifact);

        Route::get('/public-users', [AuthorizationFixtureController::class, 'index']);

        $request = \Illuminate\Http\Request::create('/public-users', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        expect(fn () => $evaluator->authorize($request))->not->toThrow(AuthorizationException::class);
    });

    it('denies methods without policy when require_policy is enabled and controller is not public', function () {
        config(['laravarc.require_policy' => true]);

        $artifact = new MetadataArtifact(
            modules: [
                'admin.user' => authorizationModulePayload(
                    controllers: [
                        AuthorizationFixtureController::class => [
                            'model' => null,
                            'policy' => null,
                            'public' => false,
                            'methods' => [],
                        ],
                    ],
                ),
            ],
        );

        $evaluator = authorizationEvaluatorWithArtifact($artifact);

        Route::get('/protected-users', [AuthorizationFixtureController::class, 'index']);

        $request = \Illuminate\Http\Request::create('/protected-users', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        expect(fn () => $evaluator->authorize($request))
            ->toThrow(AuthorizationException::class);
    });
});

/**
 * @param  array<string, array<string, mixed>>  $controllers
 * @return array<string, mixed>
 */
function authorizationModulePayload(array $controllers): array
{
    return [
        'menus' => [],
        'features' => [],
        'policy' => [
            'model' => AuthorizationFixtureModel::class,
            'policy' => AuthorizationFixturePolicy::class,
            'abilities' => ['viewAny', 'view', 'create'],
            'ability_overrides' => [],
            'controllers' => $controllers,
        ],
    ];
}

function authorizationArtifact(): MetadataArtifact
{
    return new MetadataArtifact(
        modules: [
            'admin.user' => authorizationModulePayload(
                controllers: [
                    AuthorizationFixtureController::class => [
                        'model' => null,
                        'policy' => null,
                        'public' => false,
                        'methods' => [
                            'index' => [
                                'requirements' => [
                                    ['abilities' => ['viewAny'], 'model' => null],
                                ],
                            ],
                        ],
                    ],
                ],
            ),
        ],
    );
}

function authorizationEvaluatorWithArtifact(MetadataArtifact $artifact): GatePolicyEvaluator
{
    $metadataReader = new FakeMetadataReader($artifact);

    return new GatePolicyEvaluator(
        authorizationRegistry: new CompiledAuthorizationRegistry($metadataReader),
        moduleResolver: new ControllerModuleResolver(
            modulesPath: config('laravarc.modules_path'),
            moduleNamespace: 'App\\Modules',
            moduleKeyResolver: app(\Laravarc\Core\Contracts\ModuleKeyResolver::class),
        ),
        targetResolver: new PolicyTargetResolver,
    );
}

final class AuthorizationFixtureDenyPolicy
{
    public function viewAny(?object $user): bool
    {
        return true;
    }

    public function create(?object $user): bool
    {
        return false;
    }
}
