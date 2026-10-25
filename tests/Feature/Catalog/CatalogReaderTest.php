<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Laravarc\Core\Catalog\CatalogNotInstalledException;
use Laravarc\Core\Catalog\CatalogWriter;
use Laravarc\Core\Catalog\Models\CatalogMenu;
use Laravarc\Core\Catalog\Models\CatalogMenuTranslation;
use Laravarc\Core\Contracts\CatalogReader;

describe('CatalogReader dual backend', function () {
    beforeEach(function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);
    });

    it('serves metadata labels when not installed', function () {
        $menus = app(CatalogReader::class)->menus();

        expect($menus['demo.menu']['label'])->toBe('Demo Menu');
    });

    it('serves database labels when installed', function () {
        Artisan::call('laravarc:catalog', ['action' => 'install']);
        $menu = CatalogMenu::query()->where('key', 'demo.menu')->firstOrFail();
        CatalogMenuTranslation::query()->where('menu_id', $menu->id)->update(['label' => 'DB Label']);

        $menus = app(CatalogReader::class)->menus();

        expect($menus['demo.menu']['label'])->toBe('DB Label');
    });

    it('always resolves controllers from metadata while installed', function () {
        Artisan::call('laravarc:catalog', ['action' => 'install']);

        $controllers = app(CatalogReader::class)->controllersForMenu('demo.menu');

        expect($controllers)->toContain('App\\Modules\\Demo\\Catalog\\Controllers\\DemoController');
    });

    it('throws when reordering while not installed', function () {
        expect(fn () => app(CatalogWriter::class)->reorderMenus([
            ['key' => 'demo.menu', 'order' => 1],
        ]))->toThrow(CatalogNotInstalledException::class);
    });
});
