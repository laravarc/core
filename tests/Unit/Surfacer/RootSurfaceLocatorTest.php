<?php

declare(strict_types=1);

use Laravarc\Core\Support\ModuleMetaDirectory;
use Laravarc\Core\Surfacer\RootSurfaceLocator;

describe('RootSurfaceLocator', function () {
    it('prefers StudlyCase root folder then lowercase', function () {
        $root = sys_get_temp_dir().'/laravarc-root-'.uniqid('', true);
        mkdir($root.'/admin', 0777, true);
        mkdir($root.'/Public', 0777, true);

        $locator = new RootSurfaceLocator;

        expect($locator->resolveRootDirectory($root, 'admin'))->toBe($root.'/admin')
            ->and($locator->resolveRootDirectory($root, 'public'))->toBe($root.'/Public')
            ->and($locator->resolveRootDirectory($root, 'missing'))->toBeNull();

        // cleanup
        foreach ([$root.'/admin', $root.'/Public', $root] as $dir) {
            @rmdir($dir);
        }
    });

    it('detects *_surface.php under ModuleMetaDirectory', function () {
        $root = sys_get_temp_dir().'/laravarc-surf-'.uniqid('', true);
        $meta = $root.'/Admin/'.ModuleMetaDirectory::NAME;
        mkdir($meta, 0777, true);
        file_put_contents($meta.'/admin_surface.php', "<?php\nreturn (new \\Laravarc\\Surfacer\\Definition\\SurfaceDefinition('admin'));\n");
        file_put_contents($meta.'/notes.php', '<?php');

        $locator = new RootSurfaceLocator;

        expect(ModuleMetaDirectory::NAME)->toBe('.laravarc')
            ->and($locator->hasSurface($root, 'admin'))->toBeTrue()
            ->and($locator->primarySurfaceFile($root, 'admin'))->toEndWith('admin_surface.php')
            ->and($locator->readSurfaceName($meta.'/admin_surface.php'))->toBe('admin')
            ->and($locator->surfaceNameForRoot($root, 'Admin'))->toBe('admin');

        @unlink($meta.'/admin_surface.php');
        @unlink($meta.'/notes.php');
        @rmdir($meta);
        @rmdir($root.'/Admin');
        @rmdir($root);
    });

    it('extracts root segment from module path', function () {
        $locator = new RootSurfaceLocator;
        expect($locator->rootSegmentFromModulePath('Admin/User'))->toBe('Admin')
            ->and($locator->rootSegmentFromModulePath('product'))->toBe('product');
    });
});
