<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Laravarc\Core\Facades\Catalog;
use Laravarc\Core\Catalog\CatalogSynchronizer;
use Laravarc\Core\Catalog\Models\CatalogMenu;
use Laravarc\Core\Catalog\Models\CatalogMenuTranslation;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;
use Laravarc\Core\Support\CachePath;

describe('catalog authorization index cache', function () {
    it('writes catalog-authorization.php during cache build', function () {
        expect(Artisan::call('laravarc:cache', ['action' => 'build']))->toBe(0);

        $path = CachePath::authorization();

        expect(is_file($path))->toBeTrue();

        /** @var array<string, mixed> $payload */
        $payload = include $path;

        expect($payload['schema_version'])->toBe(1)
            ->and($payload)->toHaveKeys(['surfaces', 'trees', 'policy_surfaces']);
    });

    it('exposes authorization() on catalog queries', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);

        $index = Catalog::all()->authorization();
        /** @var array<string, mixed> $payload */
        $payload = include CachePath::authorization();

        expect($index)->toBeInstanceOf(\Laravarc\Core\Catalog\CatalogAuthorizationIndex::class)
            ->and($payload['surfaces'])->not->toBeEmpty();
    });

    it('merges custom menu rows at read time without persisting DB labels in the index file', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);
        Artisan::call('laravarc:catalog', ['action' => 'install']);

        $menu = CatalogMenu::query()->create([
            'key' => 'custom.admin.menu',
            'source' => CatalogSynchronizer::SOURCE_CUSTOM,
            'order' => 99,
            'status' => 'enabled',
            'parent_key' => 'demo.menu',
            'is_orphaned' => false,
        ]);

        CatalogMenuTranslation::query()->create([
            'menu_id' => $menu->id,
            'locale' => app()->getLocale(),
            'label' => 'Custom Label From DB',
        ]);

        $manifest = Catalog::all()->manifest();

        expect($manifest['menus']['custom.admin.menu']['label'] ?? null)->toBe('Custom Label From DB');

        $fileContents = file_get_contents(CachePath::authorization());

        expect($fileContents)->not->toContain('Custom Label From DB')
            ->and($fileContents)->not->toContain('custom.admin.menu');
    });

    it('throws in production when the authorization file is missing and does not create one', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);

        $path = CachePath::authorization();
        @unlink($path);

        $previous = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            expect(fn () => Catalog::all()->authorization()->tree())
                ->toThrow(MetadataCompileException::class)
                ->and(is_file($path))->toBeFalse();
        } finally {
            app()->detectEnvironment(fn () => $previous);
        }
    });

    it('rebuilds an in-memory authorization index in testing when the file is missing', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);

        @unlink(CachePath::authorization());

        Catalog::all()->authorization()->tree();

        expect(is_file(CachePath::authorization()))->toBeFalse();
    });
});
