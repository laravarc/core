<?php

declare(strict_types=1);

use App\Modules\Admin\User\Controllers\CoreAuthFixtureController;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravarc\Core\Metadata\MetadataArtifact;
use Laravarc\Core\Tests\Fixtures\FakeMetadataReader;
use Laravarc\Core\Tests\Fixtures\Models\AuthorizationFixtureModel;

describe('laravarc.authorize middleware', function () {
    beforeEach(function () {
        Gate::policy(AuthorizationFixtureModel::class, CoreAuthFixturePolicy::class);

        app()->instance(\Laravarc\Core\Contracts\MetadataReader::class, new FakeMetadataReader(
            new MetadataArtifact(
                modules: [
                    'admin.user' => [
                        'menus' => [],
                        'features' => [],
                        'policy' => [
                            'model' => AuthorizationFixtureModel::class,
                            'policy' => CoreAuthFixturePolicy::class,
                            'abilities' => ['viewAny'],
                            'ability_overrides' => [],
                            'controllers' => [
                                CoreAuthFixtureController::class => [
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
                        ],
                    ],
                ],
            ),
        ));
    });

    it('is registered as a middleware alias', function () {
        expect(app('router')->getMiddleware()['laravarc.authorize'] ?? null)
            ->toBe(\Laravarc\Core\Http\Middleware\CoreAuthorizeMiddleware::class);
    });

    it('allows authorized requests to continue', function () {
        Route::middleware(['laravarc.authorize'])
            ->get('/arc-auth/users', [CoreAuthFixtureController::class, 'index']);

        $response = $this->get('/arc-auth/users');

        expect($response->status())->toBe(200)
            ->and($response->getContent())->toBe('ok');
    });

    it('returns forbidden when gate denies authorization', function () {
        Gate::policy(AuthorizationFixtureModel::class, CoreAuthFixtureDenyPolicy::class);

        Route::middleware(['laravarc.authorize'])
            ->get('/arc-auth/deny', [CoreAuthFixtureController::class, 'index']);

        $response = $this->get('/arc-auth/deny');

        expect($response->status())->toBe(403);
    });
});

final class CoreAuthFixturePolicy
{
    public function viewAny(?object $user): bool
    {
        return true;
    }
}

final class CoreAuthFixtureDenyPolicy
{
    public function viewAny(?object $user): bool
    {
        return false;
    }
}
