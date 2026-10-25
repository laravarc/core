<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravarc\Core\Catalog\CatalogSynchronizer;
use Laravarc\Core\Catalog\Models\CatalogMenu;
use Laravarc\Core\Support\CachePath;

describe('catalog metadata sync', function () {
    beforeEach(function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);
        Artisan::call('laravarc:catalog', ['action' => 'install']);
    });

    it('keeps locked sort order across sync', function () {
        CatalogMenu::query()->where('key', 'demo.menu')->update([
            'order' => 99,
            'order_locked' => true,
        ]);

        app(CatalogSynchronizer::class)->sync();

        expect(CatalogMenu::query()->where('key', 'demo.menu')->value('order'))->toBe(99);
    });

    it('never orphans custom menus missing from metadata', function () {
        CatalogMenu::query()->create([
            'key' => 'custom.menu',
            'source' => 'custom',
            'order' => 1,
            'status' => 'enabled',
            'is_orphaned' => false,
        ]);

        app(CatalogSynchronizer::class)->sync();

        $custom = CatalogMenu::query()->where('key', 'custom.menu')->first();
        expect($custom)->not->toBeNull()
            ->and($custom->is_orphaned)->toBeFalse()
            ->and($custom->orphaned_at)->toBeNull();
    });

    it('orphans metadata keys missing from the artifact and preserves orphaned_at', function () {
        CatalogMenu::query()->create([
            'key' => 'removed.menu',
            'source' => 'metadata',
            'order' => 1,
            'status' => 'enabled',
            'is_orphaned' => true,
            'orphaned_at' => '2026-01-01 00:00:00',
        ]);

        app(CatalogSynchronizer::class)->sync();

        $menu = CatalogMenu::query()->where('key', 'removed.menu')->first();
        expect($menu->is_orphaned)->toBeTrue()
            ->and($menu->orphaned_at?->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00');
    });

    it('clears orphan flags when a metadata key returns', function () {
        CatalogMenu::query()->where('key', 'demo.menu')->update([
            'is_orphaned' => true,
            'orphaned_at' => now(),
        ]);

        app(CatalogSynchronizer::class)->sync();

        $menu = CatalogMenu::query()->where('key', 'demo.menu')->first();
        expect($menu->is_orphaned)->toBeFalse()
            ->and($menu->orphaned_at)->toBeNull();
    });

    it('reset removes custom rows and restores metadata', function () {
        CatalogMenu::query()->create([
            'key' => 'custom.menu',
            'source' => 'custom',
            'order' => 1,
            'status' => 'enabled',
        ]);

        app()->detectEnvironment(fn () => 'testing');
        expect(Artisan::call('laravarc:catalog', ['action' => 'reset', '--force' => true]))->toBe(0);

        expect(CatalogMenu::query()->where('key', 'custom.menu')->exists())->toBeFalse()
            ->and(CatalogMenu::query()->where('key', 'demo.menu')->exists())->toBeTrue();
    });

    it('fails artisan reset on database errors', function () {
        Schema::drop('larc_menus');

        expect(Artisan::call('laravarc:catalog', ['action' => 'reset', '--force' => true]))->toBe(1);
    });
});
