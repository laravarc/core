<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Laravarc\Core\Facades\Catalog;

describe('Catalog facade', function () {
    it('forArea matches all()->forArea manifest', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);
        Artisan::call('laravarc:catalog', ['action' => 'install']);
        Cache::flush();

        $viaFacade = Catalog::forArea('admin')->manifest();
        $viaChain = Catalog::all()->forArea('admin')->manifest();

        expect($viaFacade)->toEqual($viaChain);
    });

    it('supports Orchestra facade mocking via partial mock on the manager instance', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);

        $manager = app(\Laravarc\Core\Catalog\CatalogManager::class);
        $expected = $manager->all();
        $partial = Mockery::mock($manager)->makePartial();
        $partial->shouldReceive('all')->once()->andReturn($expected);

        Catalog::swap($partial);

        expect(Catalog::all())->toBe($expected);

        Catalog::clearResolvedInstance(\Laravarc\Core\Catalog\CatalogManager::class);
    });
});
