<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Laravarc\Core\Facades\Catalog;
use Laravarc\Core\Catalog\CatalogState;
use Laravarc\Core\Catalog\Models\CatalogFeature;
use Laravarc\Core\Catalog\Models\CatalogMenu;
use Laravarc\Core\Tests\Fixtures\FakeAbilityCatalog;

describe('catalog manifest terminals', function () {
    beforeEach(function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);
    });

    it('returns the same resource keys installed or not', function () {
        $before = Catalog::all()->manifest();
        Artisan::call('laravarc:catalog', ['action' => 'install']);
        $after = Catalog::all()->manifest();

        expect(array_keys($before['menus']))->toEqual(array_keys($after['menus']))
            ->and(array_keys($before['features']))->toEqual(array_keys($after['features']));
    });

    it('omits mode routes and catalog fields', function () {
        $manifest = Catalog::all()->manifest();

        expect($manifest)->toHaveKeys(['menus', 'features', 'revision'])
            ->and($manifest)->not->toHaveKeys(['mode', 'editable', 'routes', 'catalog']);
    });

    it('hides orphaned rows by default', function () {
        Artisan::call('laravarc:catalog', ['action' => 'install']);
        CatalogMenu::query()->create([
            'key' => 'orphan.menu',
            'source' => 'metadata',
            'order' => 1,
            'status' => 'enabled',
            'is_orphaned' => true,
            'orphaned_at' => now(),
        ]);

        $manifest = Catalog::all()->manifest();
        expect($manifest['menus'])->not->toHaveKey('orphan.menu');

        $withOrphans = Catalog::all()->withOrphaned()->manifest();
        expect($withOrphans['menus'])->toHaveKey('orphan.menu');

        $onlyOrphans = Catalog::all()->onlyOrphaned()->manifest();
        expect(array_keys($onlyOrphans['menus']))->toBe(['orphan.menu']);
    });

    it('scopes manifest cache entries by revision', function () {
        Artisan::call('laravarc:catalog', ['action' => 'install']);
        $state = app(CatalogState::class);

        $before = Catalog::all()->manifest();
        $state->bumpRevision();
        $after = Catalog::all()->manifest();

        expect($before['revision'])->toBeLessThan($after['revision']);
    });

    it('filters manifest rows by exact area match', function () {
        Artisan::call('laravarc:catalog', ['action' => 'install']);
        Cache::flush();

        CatalogMenu::query()->create([
            'key' => 'area.admin.menu',
            'area' => 'admin',
            'source' => 'custom',
            'order' => 100,
            'status' => 'enabled',
        ]);
        CatalogMenu::query()->create([
            'key' => 'area.global.menu',
            'source' => 'custom',
            'order' => 101,
            'status' => 'enabled',
        ]);
        CatalogMenu::query()->create([
            'key' => 'area.user.menu',
            'area' => 'user',
            'source' => 'custom',
            'order' => 102,
            'status' => 'enabled',
        ]);

        $adminMenu = CatalogMenu::query()->where('key', 'area.admin.menu')->first();
        CatalogFeature::query()->create([
            'key' => 'area.admin.feature',
            'area' => 'admin',
            'menu_id' => $adminMenu?->id,
            'source' => 'custom',
            'order' => 1,
            'status' => 'enabled',
        ]);
        CatalogFeature::query()->create([
            'key' => 'area.user.feature',
            'area' => 'user',
            'source' => 'custom',
            'order' => 2,
            'status' => 'enabled',
        ]);

        $adminManifest = Catalog::forArea('admin')->manifest();
        expect($adminManifest['menus'])->toHaveKey('area.admin.menu')
            ->and($adminManifest['menus'])->not->toHaveKey('area.global.menu')
            ->and($adminManifest['menus'])->not->toHaveKey('area.user.menu')
            ->and($adminManifest['features'])->toHaveKey('area.admin.feature')
            ->and($adminManifest['features'])->not->toHaveKey('area.user.feature');

        $allManifest = Catalog::all()->manifest();
        expect($allManifest['menus'])->toHaveKeys(['area.admin.menu', 'area.global.menu', 'area.user.menu'])
            ->and($allManifest['features'])->toHaveKeys(['area.admin.feature', 'area.user.feature']);
    });

    it('filters menus for users without abilities', function () {
        $catalog = new FakeAbilityCatalog(
            policies: [],
            gates: [
                'App\\Modules\\Demo\\Catalog\\Controllers\\DemoController' => [
                    'index' => [['denied.policy.viewAny']],
                ],
            ],
        );
        app()->instance(\Laravarc\Core\Contracts\AbilityCatalog::class, $catalog);

        $user = new class
        {
            public function hasAbility(string $ability): bool
            {
                return false;
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }
        };

        $manifest = Catalog::forUser($user)->manifest();
        expect($manifest['menus'])->toBeEmpty();
    });
});
