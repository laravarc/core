<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravarc\Core\Catalog\CatalogState;
use Laravarc\Core\Support\CachePath;

describe('laravarc:catalog install lifecycle', function () {
    beforeEach(function () {
        Schema::create('larc_abilities', function ($table) {
            $table->id();
            $table->string('key');
        });
        Artisan::call('laravarc:cache', ['action' => 'build']);
    });

    afterEach(function () {
        app()->detectEnvironment(fn () => 'testing');
    });

    it('installs catalog tables and the state file', function () {
        expect(Artisan::call('laravarc:catalog', ['action' => 'install']))->toBe(0);

        expect(Schema::hasTable('larc_menus'))->toBeTrue()
            ->and(Schema::hasTable('larc_menu_translations'))->toBeTrue()
            ->and(Schema::hasTable('larc_features'))->toBeTrue()
            ->and(Schema::hasTable('larc_feature_translations'))->toBeTrue()
            ->and(is_file(CachePath::catalogState()))->toBeTrue()
            ->and(app(CatalogState::class)->isInstalled())->toBeTrue();

        expect(Schema::hasColumn('larc_menus', 'is_orphaned'))->toBeTrue()
            ->and(Schema::hasColumn('larc_menus', 'orphaned_at'))->toBeTrue()
            ->and(Schema::hasColumn('larc_menus', 'deleted_at'))->toBeFalse();
    });

    it('is idempotent on a second install', function () {
        Artisan::call('laravarc:catalog', ['action' => 'install']);

        expect(Artisan::call('laravarc:catalog', ['action' => 'install']))->toBe(0);
    });

    it('uninstall drops only laravarc catalog tables and keeps larc_abilities', function () {
        Artisan::call('laravarc:catalog', ['action' => 'install']);

        expect(Artisan::call('laravarc:catalog', ['action' => 'uninstall']))->toBe(0);

        expect(Schema::hasTable('larc_menus'))->toBeFalse()
            ->and(Schema::hasTable('larc_abilities'))->toBeTrue()
            ->and(is_file(CachePath::catalogState()))->toBeFalse();
    });

    it('blocks production uninstall without force', function () {
        Artisan::call('laravarc:catalog', ['action' => 'install']);
        app()->detectEnvironment(fn () => 'production');

        expect(Artisan::call('laravarc:catalog', ['action' => 'uninstall']))->toBe(1);
    });
});
