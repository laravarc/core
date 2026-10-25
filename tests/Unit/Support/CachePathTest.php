<?php

declare(strict_types=1);

use Laravarc\Core\Support\CachePath;

describe('CachePath', function () {
    afterEach(function () {
        CachePath::useBaseDirectory(null);
    });

    it('resolves locked runtime paths under bootstrap/cache/laravarc', function () {
        CachePath::useBaseDirectory(null);

        $directory = CachePath::directory();

        expect(str_ends_with($directory, 'bootstrap/cache/laravarc'))->toBeTrue()
            ->and(basename(CachePath::manifest()))->toBe('core-manifest.php')
            ->and(basename(CachePath::metadata()))->toBe('core-metadata.php')
            ->and(basename(CachePath::abilities()))->toBe('authorizer-abilities.php')
            ->and(basename(CachePath::catalogState()))->toBe('catalog-state.php')
            ->and(CachePath::manifest())->toBe($directory.'/core-manifest.php')
            ->and(CachePath::metadata())->toBe($directory.'/core-metadata.php')
            ->and(CachePath::abilities())->toBe($directory.'/authorizer-abilities.php');
    });

    it('redirects manifest, metadata, and abilities when the base directory is overridden', function () {
        $tmp = sys_get_temp_dir().'/laravarc-cache-path-'.uniqid('', true);
        mkdir($tmp, 0777, true);

        CachePath::useBaseDirectory($tmp);

        expect(CachePath::directory())->toBe($tmp)
            ->and(CachePath::manifest())->toBe($tmp.'/core-manifest.php')
            ->and(CachePath::metadata())->toBe($tmp.'/core-metadata.php')
            ->and(CachePath::abilities())->toBe($tmp.'/authorizer-abilities.php')
            ->and(CachePath::catalogState())->toBe($tmp.'/catalog-state.php');
    });

    it('restores the locked directory when the override is cleared', function () {
        $tmp = sys_get_temp_dir().'/laravarc-cache-path-'.uniqid('', true);
        mkdir($tmp, 0777, true);

        CachePath::useBaseDirectory($tmp);
        CachePath::useBaseDirectory(null);

        expect(CachePath::directory())->not->toBe($tmp)
            ->and(str_ends_with(CachePath::directory(), 'bootstrap/cache/laravarc'))->toBeTrue();
    });

    it('keeps schema directory on storage regardless of runtime override', function () {
        $expected = rtrim(str_replace('\\', '/', storage_path('framework/cache/laravarc-schema')), '/');
        $tmp = sys_get_temp_dir().'/laravarc-cache-path-'.uniqid('', true);
        mkdir($tmp, 0777, true);

        CachePath::useBaseDirectory($tmp);

        expect(CachePath::schemaDirectory())->toBe($expected);
    });
});
