<?php

declare(strict_types=1);

use Laravarc\Core\Metadata\CoreMetadataCompiler;
use Laravarc\Core\Metadata\ContractPathResolver;
use Laravarc\Core\Metadata\Exceptions\DuplicateMetadataException;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;
use Laravarc\Core\Metadata\ListenerMetadataReader;
use Laravarc\Core\Metadata\MetadataArtifact;
use Laravarc\Core\Metadata\ModuleClassDiscoverer;
use Laravarc\Core\Metadata\ReflectionMetadataReader;
use Laravarc\Core\Metadata\MetadataService;
use Laravarc\Core\Metadata\ServiceMetadataReader;
use Laravarc\Core\Metadata\Stores\FileMetadataArtifactStore;
use Laravarc\Core\Metadata\Stores\NullMetadataArtifactStore;

function metadataFixtureRoot(): string
{
    return sys_get_temp_dir().'/arc-metadata-unit-'.uniqid('', true);
}

function writeMetadataFixtureClass(string $root, string $relativePath, string $namespace, string $className, string $body): void
{
    $path = $root.'/'.str_replace('\\', '/', $relativePath);
    $directory = dirname($path);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$body}

PHP);
}

function metadataFixtureNamespace(): string
{
    return 'ArcMetadataTest\\Ns'.bin2hex(random_bytes(8));
}

function metadataServiceReader(): ServiceMetadataReader
{
    return new ServiceMetadataReader(new ContractPathResolver((string) config('laravarc.shared_path', app_path('Shared'))));
}

function emptyModuleRegistry(string $modulesPath): \Laravarc\Core\Discovery\ModuleRegistry
{
    if (! is_dir($modulesPath)) {
        mkdir($modulesPath, 0777, true);
    }

    return new \Laravarc\Core\Discovery\ModuleRegistry(
        scanner: app(\Laravarc\Core\Discovery\ModuleScanner::class),
        store: new \Laravarc\Core\Discovery\Stores\NullModuleManifestStore,
        modulesPath: $modulesPath,
        moduleNamespace: 'App\\Modules',
    );
}

function metadataListenerReader(): ListenerMetadataReader
{
    return new ListenerMetadataReader(new ModuleClassDiscoverer);
}

function metadataCompiler(
    \Laravarc\Core\Discovery\ModuleRegistry $moduleRegistry,
    \Laravarc\Core\Contracts\MetadataArtifactStore $store,
): CoreMetadataCompiler {
    return new CoreMetadataCompiler(
        moduleRegistry: $moduleRegistry,
        reader: new ReflectionMetadataReader(new ModuleClassDiscoverer),
        serviceReader: metadataServiceReader(),
        listenerReader: metadataListenerReader(),
        store: $store,
    );
}

describe('ReflectionMetadataReader', function () {
    it('reads menu feature and policy declarations from module classes', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Models/User.php',
            $namespace.'\\Models',
            'User',
            'final class User {}',
        );

        writeMetadataFixtureClass(
            $root,
            'Policies/UserPolicy.php',
            $namespace.'\\Policies',
            'UserPolicy',
            <<<'PHP'
final class UserPolicy
{
    public function viewAny(?object $user): bool
    {
        return true;
    }

    public function view(?object $user, object $model): bool
    {
        return true;
    }
}
PHP,
        );

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;
use Laravarc\Core\Metadata\Attributes\Menu;
use Laravarc\Core\Metadata\Attributes\Policy;

#[Menu(key: 'users.index', label: 'menu.users', icon: 'users', order: 10)]
#[Feature(key: 'users.manage', label: 'feature.users.manage', menu: 'users.index', placement: 'tab', order: 1)]
final class UserController
{
    #[Policy(ability: 'viewAny')]
    public function index(): void {}

    #[Policy(ability: ['create', 'viewAny'])]
    public function store(): void {}
}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);
        $result = $reader->readModule($root, $namespace, 'admin.user', 'User');

        expect($result['menus'])->toHaveCount(1)
            ->and($result['menus'][0])->toMatchArray([
                'key' => 'users.index',
                'label' => 'menu.users',
                'icon' => 'users',
                'order' => 10,
            ])
            ->and($result['menus'][0]['features'][0])->toMatchArray([
                'key' => 'users.manage',
                'label' => 'feature.users.manage',
                'placement' => 'tab',
                'order' => 1,
                'visibility_ability' => 'viewAny',
            ])
            ->and($result['features'])->toBe([])
            ->and($result['policy']['model'])->toBe($namespace.'\\Models\\User')
            ->and($result['policy']['policy'])->toBe($namespace.'\\Policies\\UserPolicy')
            ->and($result['policy']['abilities'])->toContain('viewAny', 'view', 'create')
            ->and($result['policy']['controllers'][$namespace.'\\Controllers\\UserController']['methods']['index']['requirements'][0])
            ->toMatchArray(['abilities' => ['viewAny'], 'model' => $namespace.'\\Models\\User']);
    });

    it('keeps global features at module level when menu is omitted', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Models/User.php',
            $namespace.'\\Models',
            'User',
            'final class User {}',
        );

        writeMetadataFixtureClass(
            $root,
            'Policies/UserPolicy.php',
            $namespace.'\\Policies',
            'UserPolicy',
            'final class UserPolicy { public function viewAny(?object $user): bool { return true; } }',
        );

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'global.widget', label: 'feature.global.widget', placement: 'panel', order: 5)]
final class UserController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);
        $result = $reader->readModule($root, $namespace, 'admin.user', 'User');

        expect($result['features'][0])->toMatchArray([
            'key' => 'global.widget',
            'label' => 'feature.global.widget',
            'placement' => 'panel',
            'order' => 5,
            'visibility_ability' => 'viewAny',
        ]);
    });

    it('honours explicit Feature visibilityAbility instead of defaulting to viewAny', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Policies/CatalogPolicy.php',
            $namespace.'\\Policies',
            'CatalogPolicy',
            <<<'PHP'
final class CatalogPolicy
{
    public function show(?object $actor): bool
    {
        return true;
    }

    public function sync(?object $actor): bool
    {
        return true;
    }
}
PHP,
        );

        writeMetadataFixtureClass(
            $root,
            'Controllers/CatalogController.php',
            $namespace.'\\Controllers',
            'CatalogController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.catalog.sync', label: 'Sync Catalog', placement: 'admin', order: 5, visibilityAbility: 'sync')]
final class CatalogController
{
    public function sync(): void {}
}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);
        $result = $reader->readModule($root, $namespace, 'admin.catalog', 'Catalog');

        expect($result['features'][0])->toMatchArray([
            'key' => 'admin.catalog.sync',
            'label' => 'Sync Catalog',
            'placement' => 'admin',
            'order' => 5,
            'visibility_ability' => 'sync',
        ]);
    });

    it('fails compile on duplicate menu keys', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Policies/UserPolicy.php',
            $namespace.'\\Policies',
            'UserPolicy',
            'final class UserPolicy { public function viewAny(?object $user): bool { return true; } }',
        );

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'users.index', label: 'menu.users')]
final class UserController {}
PHP,
        );

        writeMetadataFixtureClass(
            $root,
            'Controllers/ProfileController.php',
            $namespace.'\\Controllers',
            'ProfileController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'users.index', label: 'menu.users.duplicate')]
final class ProfileController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);

        expect(fn () => $reader->readModule($root, $namespace, 'admin.user', 'User'))
            ->toThrow(DuplicateMetadataException::class, 'Duplicate menu key [users.index]');
    });

    it('fails compile when policy metadata cannot resolve a target model', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Policy;

final class UserController
{
    #[Policy(ability: 'viewAny')]
    public function index(): void {}
}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);

        expect(fn () => $reader->readModule($root, $namespace, 'admin.user', 'User'))
            ->toThrow(MetadataCompileException::class, 'could not resolve a target model');
    });

    it('allows null module defaults when convention files are absent', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            'final class UserController {}',
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);
        $result = $reader->readModule($root, $namespace, 'admin.user', 'User');

        expect($result['policy']['model'])->toBeNull()
            ->and($result['policy']['policy'])->toBeNull();
    });
});

describe('MetadataArtifact', function () {
    it('round-trips through array representation', function () {
        $artifact = new MetadataArtifact(
            modules: [
                'admin.user' => [
                    'menus' => [['key' => 'users.index', 'label' => 'menu.users', 'features' => []]],
                    'features' => [],
                    'policy' => [
                        'model' => 'App\\Modules\\Admin\\User\\Models\\User',
                        'policy' => 'App\\Modules\\Admin\\User\\Policies\\UserPolicy',
                        'abilities' => ['viewAny'],
                        'ability_overrides' => [],
                        'controllers' => [],
                    ],
                    'services' => [],
                ],
            ],
            compiledAt: '2026-07-07T00:00:00+00:00',
        );

        expect(MetadataArtifact::fromArray($artifact->toArray())->toArray())
            ->toBe($artifact->toArray());
    });

    it('supports empty modules artifact', function () {
        expect(MetadataArtifact::empty()->toArray()['modules'])->toBe([]);
    });
});

describe('FileMetadataArtifactStore', function () {
    it('writes and reads php metadata artifact', function () {
        $path = metadataFixtureRoot().'/metadata.php';
        $store = new FileMetadataArtifactStore($path);
        $artifact = MetadataArtifact::empty();

        $store->write($artifact);

        expect($store->read()?->toArray())->toBe($artifact->toArray())
            ->and(is_file($path))->toBeTrue();
    });
});

describe('MetadataService', function () {
    it('reflects metadata on demand when store driver is null', function () {
        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry(metadataFixtureRoot()),
            store: new NullMetadataArtifactStore,
        );

        $service = new MetadataService(
            store: new NullMetadataArtifactStore,
            compiler: $compiler,
        );

        expect($service->artifact()->modules)->toBe([]);
    });
});

describe('CoreMetadataCompiler zero modules', function () {
    it('produces empty modules artifact', function () {
        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry(metadataFixtureRoot()),
            store: new FileMetadataArtifactStore(metadataFixtureRoot().'/metadata.php'),
        );

        $result = $compiler->compile();

        expect($result->moduleCount)->toBe(0)
            ->and($result->artifact->modules)->toBe([]);
    });
});

describe('ServiceMetadataReader', function () {
    it('returns command and query bindings when concrete and contract files exist', function () {
        $modulesPath = metadataFixtureRoot().'/modules';
        $moduleRoot = $modulesPath.'/Admin/User';
        $namespace = 'App\\Modules\\Admin\\User';
        $sharedRelative = 'ArcMetadataTest/Shared';
        $sharedRoot = app_path($sharedRelative.'/Admin/User/Contracts');

        mkdir($moduleRoot.'/Services/Commands', 0777, true);
        mkdir($moduleRoot.'/Services/Queries', 0777, true);
        if (! is_dir($sharedRoot)) {
            mkdir($sharedRoot, 0777, true);
        }

        file_put_contents($moduleRoot.'/Services/Commands/UserCommandService.php', '<?php declare(strict_types=1); namespace '.$namespace.'\\Services\\Commands; final class UserCommandService {}');
        file_put_contents($moduleRoot.'/Services/Queries/UserQueryService.php', '<?php declare(strict_types=1); namespace '.$namespace.'\\Services\\Queries; final class UserQueryService {}');
        file_put_contents($sharedRoot.'/UserCommandServiceContract.php', '<?php declare(strict_types=1); namespace App\\ArcMetadataTest\\Shared\\Admin\\User\\Contracts; interface UserCommandServiceContract {}');
        file_put_contents($sharedRoot.'/UserQueryServiceContract.php', '<?php declare(strict_types=1); namespace App\\ArcMetadataTest\\Shared\\Admin\\User\\Contracts; interface UserQueryServiceContract {}');

        config(['laravarc.shared_path' => $sharedRelative]);
        config(['laravarc.modules_path' => $modulesPath]);

        $registry = emptyModuleRegistry($modulesPath);
        $entry = $registry->requireByPath('admin/user');
        $reader = new ServiceMetadataReader(new ContractPathResolver((string) config('laravarc.shared_path')));

        expect($reader->readModule($entry))->toBe([
            [
                'concrete' => $namespace.'\\Services\\Commands\\UserCommandService',
                'contract' => 'App\\ArcMetadataTest\\Shared\\Admin\\User\\Contracts\\UserCommandServiceContract',
                'kind' => 'command',
            ],
            [
                'concrete' => $namespace.'\\Services\\Queries\\UserQueryService',
                'contract' => 'App\\ArcMetadataTest\\Shared\\Admin\\User\\Contracts\\UserQueryServiceContract',
                'kind' => 'query',
            ],
        ]);
    });

    it('omits bindings when only the concrete class exists', function () {
        $modulesPath = metadataFixtureRoot().'/modules';
        $moduleRoot = $modulesPath.'/Admin/Report';
        $namespace = 'App\\Modules\\Admin\\Report';

        mkdir($moduleRoot.'/Services/Commands', 0777, true);
        file_put_contents($moduleRoot.'/Services/Commands/ReportCommandService.php', '<?php declare(strict_types=1); namespace '.$namespace.'\\Services\\Commands; final class ReportCommandService {}');

        config(['laravarc.modules_path' => $modulesPath]);
        config(['laravarc.shared_path' => 'ArcMetadataTest/SharedOnlyConcrete']);

        $registry = emptyModuleRegistry($modulesPath);
        $entry = $registry->requireByPath('admin/report');
        $reader = metadataServiceReader();

        expect($reader->readModule($entry))->toBe([])
            ->and($reader->hasServiceSignals($entry))->toBeTrue();
    });
});
