<?php

declare(strict_types=1);

use App\Modules\Admin\User\Controllers\CoreAuthFixtureController;
use Illuminate\Support\Facades\Route;
use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Metadata\MetadataArtifact;
use Laravarc\Core\Tests\Fixtures\FakeAbilityCatalog;
use Laravarc\Core\Tests\Fixtures\FakeAuthUser;
use Laravarc\Core\Tests\Fixtures\FakeMetadataReader;

describe('laravarc.authorize middleware', function () {
    beforeEach(function () {
        app()->instance(MetadataReader::class, new FakeMetadataReader(
            new MetadataArtifact(
                modules: [
                    'admin.user' => [
                        'controllers' => [
                            'core_auth_fixture' => [
                                'controller' => CoreAuthFixtureController::class,
                                'access' => 'authorized',
                                'menu' => null,
                                'features' => [],
                            ],
                        ],
                    ],
                ],
            ),
        ));

        app()->instance(AbilityCatalog::class, new FakeAbilityCatalog(
            gates: [
                CoreAuthFixtureController::class => [
                    'index' => [['app.modules.admin.user.policies.core_auth_fixture_policy.viewAny']],
                ],
            ],
        ));
    });

    it('is registered as a middleware alias', function () {
        expect(app('router')->getMiddleware()['laravarc.authorize'] ?? null)
            ->toBe(\Laravarc\Core\Http\Middleware\CoreAuthorizeMiddleware::class);
    });

    it('allows authorized requests to continue', function () {
        $this->actingAs(new FakeAuthUser(['app.modules.admin.user.policies.core_auth_fixture_policy.viewAny']));

        Route::middleware(['laravarc.authorize'])
            ->get('/arc-auth/users', [CoreAuthFixtureController::class, 'index']);

        $response = $this->get('/arc-auth/users');

        expect($response->status())->toBe(200)
            ->and($response->getContent())->toBe('ok');
    });

    it('returns forbidden when gate denies authorization', function () {
        $this->actingAs(new FakeAuthUser([]));

        Route::middleware(['laravarc.authorize'])
            ->get('/arc-auth/deny', [CoreAuthFixtureController::class, 'index']);

        $response = $this->get('/arc-auth/deny');

        expect($response->status())->toBe(403);
    });

    it('returns forbidden for anonymous requests on gated methods', function () {
        Route::middleware(['laravarc.authorize'])
            ->get('/arc-auth/anonymous', [CoreAuthFixtureController::class, 'index']);

        $response = $this->get('/arc-auth/anonymous');

        expect($response->status())->toBe(403);
    });
});
