<?php

declare(strict_types=1);

use App\Modules\Admin\User\Controllers\AuthorizationFixtureController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravarc\Core\Authorization\ControllerModuleResolver;
use Laravarc\Core\Authorization\GatePolicyEvaluator;
use Laravarc\Core\Metadata\MetadataArtifact;
use Laravarc\Core\Tests\Fixtures\FakeAbilityCatalog;
use Laravarc\Core\Tests\Fixtures\FakeAuthUser;
use Laravarc\Core\Tests\Fixtures\FakeMetadataReader;

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
    it('authorizes when every ability in a DNF group is granted', function () {
        $evaluator = evaluatorWith(gates: [
            AuthorizationFixtureController::class => [
                'index' => [['app.modules.admin.user.policies.user_policy.viewAny']],
            ],
        ]);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith(['app.modules.admin.user.policies.user_policy.viewAny']))))
            ->not->toThrow(AuthorizationException::class);
    });

    it('denies when an ability in the group is missing', function () {
        $evaluator = evaluatorWith(gates: [
            AuthorizationFixtureController::class => [
                'index' => [['app.modules.admin.user.policies.user_policy.viewAny']],
            ],
        ]);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith([]))))
            ->toThrow(AuthorizationException::class);
    });

    it('passes when any OR group is fully granted', function () {
        $evaluator = evaluatorWith(gates: [
            AuthorizationFixtureController::class => [
                'index' => [
                    ['policy.a', 'policy.b'],
                    ['policy.c'],
                ],
            ],
        ]);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith(['policy.c']))))
            ->not->toThrow(AuthorizationException::class);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith(['policy.a']))))
            ->toThrow(AuthorizationException::class);
    });

    it('bypasses gates for super users', function () {
        $evaluator = evaluatorWith(gates: [
            AuthorizationFixtureController::class => [
                'index' => [['policy.missing']],
            ],
        ]);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith([], super: true))))
            ->not->toThrow(AuthorizationException::class);
    });

    it('allows authenticated controllers without gates', function () {
        $evaluator = evaluatorWith(access: 'authenticated', gates: []);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith([]))))
            ->not->toThrow(AuthorizationException::class);
    });

    it('denies authorized controllers when no gate is compiled', function () {
        $evaluator = evaluatorWith(access: 'authorized', gates: []);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith([]))))
            ->toThrow(AuthorizationException::class);
    });

    it('treats an ability override as a single group', function () {
        $evaluator = evaluatorWith(gates: []);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith(['policy.override'])), 'policy.override'))
            ->not->toThrow(AuthorizationException::class);

        expect(fn () => $evaluator->authorize(requestFor('index', userWith([])), 'policy.override'))
            ->toThrow(AuthorizationException::class);
    });

    it('denies when the module is missing from metadata', function () {
        $evaluator = new GatePolicyEvaluator(
            metadata: new FakeMetadataReader(MetadataArtifact::empty()),
            catalog: new FakeAbilityCatalog,
            moduleResolver: moduleResolver(),
        );

        expect(fn () => $evaluator->authorize(requestFor('index', userWith([]))))
            ->toThrow(AuthorizationException::class);
    });
});

/**
 * @param  array<string, array<string, list<list<string>>>>  $gates
 */
function evaluatorWith(string $access = 'authorized', array $gates = []): GatePolicyEvaluator
{
    $artifact = new MetadataArtifact(
        modules: [
            'admin.user' => [
                'controllers' => [
                    'authorization_fixture' => [
                        'controller' => AuthorizationFixtureController::class,
                        'access' => $access,
                        'menu' => null,
                        'features' => [],
                    ],
                ],
            ],
        ],
    );

    return new GatePolicyEvaluator(
        metadata: new FakeMetadataReader($artifact),
        catalog: new FakeAbilityCatalog(gates: $gates),
        moduleResolver: moduleResolver(),
    );
}

function moduleResolver(): ControllerModuleResolver
{
    return new ControllerModuleResolver(
        modulesPath: config('laravarc.modules_path'),
        moduleNamespace: 'App\\Modules',
        moduleKeyResolver: app(\Laravarc\Core\Contracts\ModuleKeyResolver::class),
    );
}

/**
 * @param  list<string>  $abilities
 */
function userWith(array $abilities, bool $super = false): FakeAuthUser
{
    return new FakeAuthUser($abilities, $super);
}

function requestFor(string $method, FakeAuthUser $user): Request
{
    $uri = '/auth-fixture-'.uniqid();
    Route::get($uri, [AuthorizationFixtureController::class, $method]);

    $request = Request::create($uri, 'GET');
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    $request->setUserResolver(fn () => $user);

    return $request;
}
