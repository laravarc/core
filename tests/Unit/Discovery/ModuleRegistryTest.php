<?php

declare(strict_types=1);

use Laravarc\Core\Convention\DefaultModuleKeyResolver;
use Laravarc\Core\Discovery\ModuleManifestStoreFactory;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Discovery\ModuleScanner;
use Laravarc\Core\Discovery\ModuleServiceProviderResolver;
use Laravarc\Core\Discovery\Stores\FileModuleManifestStore;
use Laravarc\Core\Discovery\Stores\JsonModuleManifestStore;
use Laravarc\Core\Discovery\Stores\NullModuleManifestStore;

function createDiscoveryFixtureRoot(): string
{
    $root = sys_get_temp_dir().'/arc-discovery-'.uniqid('', true);
    mkdir($root, 0777, true);

    return $root;
}

function createModuleFixture(string $modulesRoot, string $path, array $signalPaths = ['Controllers']): void
{
    $moduleRoot = $modulesRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);

    foreach ($signalPaths as $signalPath) {
        $directory = $moduleRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $signalPath);
        mkdir($directory, 0777, true);
    }
}

function createModulePrimaryServiceProviderFixture(
    string $modulesRoot,
    string $path,
    string $namespace = 'App\\Modules',
    ?string $registrationTrackKey = null,
): void {
    createModuleFixture($modulesRoot, $path);

    $moduleRootOnDisk = $modulesRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, trim($path, '/'));
    $identity = \Laravarc\Core\Module\ModuleIdentity::fromPath(
        $path,
        $modulesRoot,
        $namespace,
        rootPathOverride: $moduleRootOnDisk,
    );
    $basename = \Illuminate\Support\Str::studly($identity->segments[array_key_last($identity->segments)] ?? 'Module');
    $providersDir = $identity->rootPath.'/Providers';
    mkdir($providersDir, 0777, true);

    $className = $basename.'ServiceProvider';
    $fqcn = $identity->namespace.'\\Providers\\'.$className;
    $registerBody = $registrationTrackKey === null
        ? ''
        : <<<PHP

    public function register(): void
    {
        \\Laravarc\\Core\\Tests\\Support\\ModuleProviderRegistrationOrder::\$order[] = '{$registrationTrackKey}';
    }
PHP;

    file_put_contents($providersDir.'/'.$className.'.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$identity->namespace}\\Providers;

use Illuminate\\Support\\ServiceProvider;
use Laravarc\\Core\\Contracts\\ModuleServiceProviderContract;

final class {$className} extends ServiceProvider implements ModuleServiceProviderContract
{
    public static function modulePath(): string
    {
        return '{$identity->path}';
    }{$registerBody}
}
PHP);

    require_once $providersDir.'/'.$className.'.php';

    expect(class_exists($fqcn))->toBeTrue();
}

function removeDirectoryTree(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}

describe('ModuleScanner', function () {
    beforeEach(function () {
        $this->modulesRoot = createDiscoveryFixtureRoot();
        $this->scanner = new ModuleScanner(new DefaultModuleKeyResolver, new ModuleServiceProviderResolver);
        $this->discoveredAt = '2026-07-07T12:00:00+00:00';
    });

    afterEach(function () {
        removeDirectoryTree($this->modulesRoot);
    });

    it('discovers modules with structural signals at nested paths', function () {
        createModuleFixture($this->modulesRoot, 'admin/user');
        createModuleFixture($this->modulesRoot, 'product');

        $entries = $this->scanner->scan($this->modulesRoot, 'App\\Modules', $this->discoveredAt);

        expect($entries)->toHaveCount(2)
            ->and($entries[0]->path)->toBe('Admin/User')
            ->and($entries[0]->key)->toBe('admin.user')
            ->and($entries[0]->namespace)->toBe('App\\Modules\\Admin\\User')
            ->and($entries[1]->path)->toBe('Product');
    });

    it('discovers migration-only modules', function () {
        createModuleFixture($this->modulesRoot, 'draft/order', ['Database/Migrations']);

        $entries = $this->scanner->scan($this->modulesRoot, 'App\\Modules', $this->discoveredAt);

        expect($entries)->toHaveCount(1)
            ->and($entries[0]->path)->toBe('Draft/Order');
    });

    it('ignores empty directories and grouping folders without signals', function () {
        mkdir($this->modulesRoot.'/admin', 0777, true);
        createModuleFixture($this->modulesRoot, 'admin/user');

        $entries = $this->scanner->scan($this->modulesRoot, 'App\\Modules', $this->discoveredAt);

        expect($entries)->toHaveCount(1)
            ->and($entries[0]->path)->toBe('Admin/User');
    });

    it('ignores symlinked directories outside modules root', function () {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('Symlinks are not supported in this environment.');
        }

        $outside = createDiscoveryFixtureRoot();
        createModuleFixture($outside, 'outside-module');
        symlink($outside.'/outside-module', $this->modulesRoot.'/linked-module');

        $entries = $this->scanner->scan($this->modulesRoot, 'App\\Modules', $this->discoveredAt);

        expect($entries)->toHaveCount(0);

        removeDirectoryTree($outside);
    });
});

describe('ModuleRegistry', function () {
    beforeEach(function () {
        $this->modulesRoot = createDiscoveryFixtureRoot();
        $this->manifestPath = $this->modulesRoot.'/manifest.php';
        $this->scanner = new ModuleScanner(new DefaultModuleKeyResolver, new ModuleServiceProviderResolver);
        $this->store = new FileModuleManifestStore($this->manifestPath);
        $this->registry = new ModuleRegistry(
            scanner: $this->scanner,
            store: $this->store,
            modulesPath: $this->modulesRoot,
            moduleNamespace: 'App\\Modules',
        );
    });

    afterEach(function () {
        removeDirectoryTree($this->modulesRoot);
    });

    it('refreshes and persists manifest entries', function () {
        createModuleFixture($this->modulesRoot, 'admin/user');

        $manifest = $this->registry->refresh();

        expect($manifest->all())->toHaveCount(1)
            ->and(is_file($this->manifestPath))->toBeTrue()
            ->and($this->registry->findByPath('admin/user')?->path)->toBe('Admin/User');
    });

    it('reads manifest without rescanning when persistent store is warm', function () {
        createModuleFixture($this->modulesRoot, 'product');
        $this->registry->refresh();

        removeDirectoryTree($this->modulesRoot.'/product');

        expect($this->registry->manifest()->findByPath('product')?->path)->toBe('Product');
    });

    it('clears persisted manifest artifacts', function () {
        createModuleFixture($this->modulesRoot, 'product');
        $this->registry->refresh();

        $this->registry->clear();

        expect(is_file($this->manifestPath))->toBeFalse();
    });

    it('returns not-found for unknown module paths', function () {
        expect($this->registry->findByPath('missing/module'))->toBeNull();
    });

    it('captures primary module service providers during refresh', function () {
        createModulePrimaryServiceProviderFixture($this->modulesRoot, 'admin/platform/catalog');

        $manifest = $this->registry->refresh();
        $entry = $manifest->findByPath('Admin/Platform/Catalog');

        expect($entry)->not->toBeNull()
            ->and($entry?->providers)->toBe([
                'App\\Modules\\Admin\\Platform\\Catalog\\Providers\\CatalogServiceProvider',
            ]);
    });

    it('rejects primary service providers that do not implement ModuleServiceProviderContract', function () {
        createModuleFixture($this->modulesRoot, 'admin/bad');
        $moduleRootOnDisk = $this->modulesRoot.'/admin/bad';
        $identity = \Laravarc\Core\Module\ModuleIdentity::fromPath(
            'admin/bad',
            $this->modulesRoot,
            'App\\Modules',
            rootPathOverride: $moduleRootOnDisk,
        );
        $providersDir = $identity->rootPath.'/Providers';
        mkdir($providersDir, 0777, true);

        file_put_contents($providersDir.'/BadServiceProvider.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Admin\Bad\Providers;

use Illuminate\Support\ServiceProvider;

final class BadServiceProvider extends ServiceProvider {}
PHP);

        expect(fn () => $this->registry->refresh())
            ->toThrow(\Laravarc\Core\Discovery\Exceptions\ModuleScanException::class);
    });

    it('registers module service providers in sorted module path order', function () {
        \Laravarc\Core\Tests\Support\ModuleProviderRegistrationOrder::reset();

        createModulePrimaryServiceProviderFixture($this->modulesRoot, 'zeta/module', registrationTrackKey: 'zeta');
        createModulePrimaryServiceProviderFixture($this->modulesRoot, 'alpha/module', registrationTrackKey: 'alpha');

        $this->registry->refresh();

        $loader = new \Laravarc\Core\Discovery\ModuleServiceProviderLoader(
            moduleRegistry: $this->registry,
            app: app(),
            enabled: true,
        );

        $loader->load();

        expect(\Laravarc\Core\Tests\Support\ModuleProviderRegistrationOrder::$order)->toBe(['alpha', 'zeta']);
    });
});

describe('ModuleManifestStoreFactory', function () {
    it('creates file, json, and null stores', function () {
        $factory = new ModuleManifestStoreFactory;

        expect($factory->make('file', '/tmp/a.php', '/tmp/a.json'))
            ->toBeInstanceOf(FileModuleManifestStore::class)
            ->and($factory->make('json', '/tmp/a.php', '/tmp/a.json'))
            ->toBeInstanceOf(JsonModuleManifestStore::class)
            ->and($factory->make('null', '/tmp/a.php', '/tmp/a.json'))
            ->toBeInstanceOf(NullModuleManifestStore::class)
            ->and($factory->make('null', '/tmp/a.php', '/tmp/a.json')->isPersistent())
            ->toBeFalse();
    });
});

describe('NullModuleManifestStore registry', function () {
    it('rescans on each manifest read', function () {
        $modulesRoot = createDiscoveryFixtureRoot();
        createModuleFixture($modulesRoot, 'catalog/item');

        $registry = new ModuleRegistry(
            scanner: new ModuleScanner(new DefaultModuleKeyResolver, new ModuleServiceProviderResolver),
            store: new NullModuleManifestStore,
            modulesPath: $modulesRoot,
            moduleNamespace: 'App\\Modules',
        );

        expect($registry->manifest()->all())->toHaveCount(1);

        createModuleFixture($modulesRoot, 'catalog/tag');

        expect($registry->manifest()->all())->toHaveCount(2);

        removeDirectoryTree($modulesRoot);
    });
});
