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
use Laravarc\Core\Metadata\Attributes\Menu;
use Laravarc\Core\Metadata\Attributes\Policy;

#[Menu(key: 'users.index', label: 'menu.users', icon: 'users', order: 10)]
final class UserController
{
    #[Policy(ability: 'viewAny')]
    public function index(): void {}

    #[Policy(ability: ['create', 'viewAny'])]
    public function store(): void {}
}
PHP,
        );

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserManageController.php',
            $namespace.'\\Controllers',
            'UserManageController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'users.manage', label: 'feature.users.manage', menu: 'users.index', placement: 'tab', order: 1)]
final class UserManageController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);
        $result = $reader->readModule($root, $namespace, 'admin.user', 'User');

        $controller = $result['controllers']['user'] ?? null;
        $manageController = $result['controllers']['user_manage'] ?? null;

        expect($controller)->not->toBeNull()
            ->and($controller['access'])->toBe('authorized')
            ->and($controller['menu'])->toMatchArray([
                'key' => 'users.index',
                'label' => 'menu.users',
                'icon' => 'users',
                'order' => 10,
            ])
            ->and($controller['features'])->toBe([])
            ->and($manageController)->not->toBeNull()
            ->and($manageController['features'][0])->toMatchArray([
                'key' => 'users.manage',
                'label' => 'feature.users.manage',
                'menu' => 'users.index',
                'placement' => 'tab',
                'order' => 1,
            ])
            ->and($result['authorization']['policy'])->toBe($namespace.'\\Policies\\UserPolicy')
            ->and($result['authorization']['controllers'][$namespace.'\\Controllers\\UserController']['requirements']['index'][0])
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

        expect($result['controllers']['user']['features'][0])->toMatchArray([
            'key' => 'global.widget',
            'label' => 'feature.global.widget',
            'placement' => 'panel',
            'order' => 5,
        ])
            ->and($result['controllers']['user']['menu'])->toBeNull();
    });

    it('keeps cross-module feature references flat with menu retained', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Controllers/BrandController.php',
            $namespace.'\\Controllers',
            'BrandController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'brand.manage', label: 'feature.brand.manage', menu: 'admin.inventory.product-mutation', placement: 'tab', order: 3)]
final class BrandController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);
        $result = $reader->readModule($root, $namespace, 'admin.brand', 'Brand');

        expect($result['controllers']['brand']['menu'])->toBeNull()
            ->and($result['controllers']['brand']['features'])->toHaveCount(1)
            ->and($result['controllers']['brand']['features'][0])->toMatchArray([
                'key' => 'brand.manage',
                'label' => 'feature.brand.manage',
                'menu' => 'admin.inventory.product-mutation',
                'placement' => 'tab',
                'order' => 3,

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

    it('rejects controllers that mix access kinds', function () {
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
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Policy;

final class UserController
{
    #[Policy(ability: 'viewAny')]
    public function index(): void {}

    public function store(): void {}
}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);

        expect(fn () => $reader->readModule($root, $namespace, 'admin.user', 'User'))
            ->toThrow(MetadataCompileException::class, 'mixes access kinds');
    });

    it('rejects a feature that duplicates its own menu key', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'users.index', label: 'Users')]
#[Feature(key: 'users.index', label: 'Users tab', menu: 'users.index', placement: 'tab')]
final class UserController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);

        expect(fn () => $reader->readModule($root, $namespace, 'admin.user', 'User'))
            ->toThrow(MetadataCompileException::class, 'duplicates its own menu key');
    });

    it('rejects more than one Menu attribute on a controller', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'users.index', label: 'Users')]
#[Menu(key: 'users.other', label: 'Other')]
final class UserController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);

        expect(fn () => $reader->readModule($root, $namespace, 'admin.user', 'User'))
            ->toThrow(MetadataCompileException::class, 'at most one is allowed');
    });

    it('rejects an empty feature menu string', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'users.manage', label: 'Manage', menu: '', placement: 'tab')]
final class UserController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);

        expect(fn () => $reader->readModule($root, $namespace, 'admin.user', 'User'))
            ->toThrow(MetadataCompileException::class, 'non-empty menu key');
    });

    it('rejects a feature menu that matches the menu key on the same controller', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'users.index', label: 'Users')]
#[Feature(key: 'users.manage', label: 'Manage', menu: 'users.index', placement: 'tab')]
final class UserController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);

        expect(fn () => $reader->readModule($root, $namespace, 'admin.user', 'User'))
            ->toThrow(MetadataCompileException::class, 'users.index');
    });

    it('reads multiple Feature attributes on one controller', function () {
        $root = metadataFixtureRoot();
        $namespace = metadataFixtureNamespace();

        writeMetadataFixtureClass(
            $root,
            'Controllers/UserController.php',
            $namespace.'\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'global.one', label: 'One', placement: 'panel', order: 1)]
#[Feature(key: 'global.two', label: 'Two', placement: 'panel', order: 2)]
final class UserController {}
PHP,
        );

        $reader = new ReflectionMetadataReader(new ModuleClassDiscoverer);
        $result = $reader->readModule($root, $namespace, 'admin.user', 'User');

        expect($result['controllers']['user']['features'])->toHaveCount(2)
            ->and($result['controllers']['user']['features'][0]['key'])->toBe('global.one')
            ->and($result['controllers']['user']['features'][1]['key'])->toBe('global.two');
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

        expect($result['authorization']['model'])->toBeNull()
            ->and($result['authorization']['policy'])->toBeNull();
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

    it('rejects an unsupported schema_version', function () {
        expect(fn () => MetadataArtifact::fromArray([
            'schema_version' => 99,
            'modules' => [],
        ]))->toThrow(\Laravarc\Core\Metadata\Exceptions\CorruptMetadataArtifactException::class, 'schema_version');
    });

    it('rejects a payload with a missing schema_version', function () {
        expect(fn () => MetadataArtifact::fromArray(['modules' => []]))
            ->toThrow(\Laravarc\Core\Metadata\Exceptions\CorruptMetadataArtifactException::class, 'missing');
    });

    it('rejects a non-array modules payload', function () {
        expect(fn () => MetadataArtifact::fromArray([
            'schema_version' => MetadataArtifact::SCHEMA_VERSION,
            'modules' => 'invalid',
        ]))->toThrow(\Laravarc\Core\Metadata\Exceptions\CorruptMetadataArtifactException::class, 'modules');
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

describe('CoreMetadataCompiler path fallback warnings', function () {
    it('warns for menus and nested features that rely on the derived path', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/User/Controllers/UserController.php',
            'App\\Modules\\Admin\\User\\Controllers',
            'UserController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'admin.users', label: 'Users')]
final class UserController {}
PHP,
        );

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/User/Controllers/UserRolesController.php',
            'App\\Modules\\Admin\\User\\Controllers',
            'UserRolesController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.users.roles', label: 'Roles', menu: 'admin.users', placement: 'tab', order: 1)]
final class UserRolesController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        $result = $compiler->compile();

        expect($result->warnings)->toHaveCount(2)
            ->and(implode("\n", $result->warnings))->toContain('Menu [admin.users] in module [admin.user]')
            ->and(implode("\n", $result->warnings))->toContain('Feature [admin.users.roles] in module [admin.user]');
    });

    it('does not warn when explicit paths are present', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Team/Controllers/TeamController.php',
            'App\\Modules\\Admin\\Team\\Controllers',
            'TeamController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'admin.teams', label: 'Teams', path: '/teams')]
final class TeamController {}
PHP,
        );

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Team/Controllers/TeamRolesController.php',
            'App\\Modules\\Admin\\Team\\Controllers',
            'TeamRolesController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.teams.roles', label: 'Roles', menu: 'admin.teams', placement: 'tab', order: 1, path: '/roles')]
final class TeamRolesController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        $result = $compiler->compile();

        expect($result->warnings)->toBe([])
            ->and($result->artifact->modules['admin.team']['controllers']['team']['menu'])
            ->toMatchArray(['key' => 'admin.teams', 'path' => '/teams'])
            ->and($result->artifact->modules['admin.team']['controllers']['team_roles']['features'][0])
            ->toMatchArray(['key' => 'admin.teams.roles', 'path' => '/roles']);
    });

    it('normalises a repeated menu prefix in a feature path', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Repeat/Controllers/RepeatController.php',
            'App\\Modules\\Admin\\Repeat\\Controllers',
            'RepeatController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'admin.repeat', label: 'Repeat', path: '/repeat')]
final class RepeatController {}
PHP,
        );

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Repeat/Controllers/RepeatTabController.php',
            'App\\Modules\\Admin\\Repeat\\Controllers',
            'RepeatTabController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.repeat.tab', label: 'Tab', menu: 'admin.repeat', placement: 'tab', path: '/repeat/tab')]
final class RepeatTabController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        $result = $compiler->compile();

        expect($result->artifact->modules['admin.repeat']['controllers']['repeat_tab']['features'][0])
            ->toMatchArray(['key' => 'admin.repeat.tab', 'path' => '/repeat/tab']);
    });

    it('fails compile when a feature has a path but its menu has no path', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Header/Controllers/HeaderController.php',
            'App\\Modules\\Admin\\Header\\Controllers',
            'HeaderController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'admin.header', label: 'Header')]
final class HeaderController {}
PHP,
        );

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Header/Controllers/HeaderTabController.php',
            'App\\Modules\\Admin\\Header\\Controllers',
            'HeaderTabController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.header.tab', label: 'Tab', menu: 'admin.header', placement: 'tab', path: '/tab')]
final class HeaderTabController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        expect(fn () => $compiler->compile())
            ->toThrow(MetadataCompileException::class, 'requires a parent menu path');
    });
});

describe('CoreMetadataCompiler feature menu validation', function () {
    it('compiles cross-module feature references and keeps them flat with menu', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/ProductMutation/Controllers/ProductMutationController.php',
            'App\\Modules\\Admin\\ProductMutation\\Controllers',
            'ProductMutationController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'admin.inventory.product-mutation', label: 'menu.product-mutation')]
final class ProductMutationController {}
PHP,
        );

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Brand/Controllers/BrandController.php',
            'App\\Modules\\Admin\\Brand\\Controllers',
            'BrandController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.inventory.brand', label: 'feature.brand', menu: 'admin.inventory.product-mutation', placement: 'tab', order: 1)]
final class BrandController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        $result = $compiler->compile();

        expect($result->artifact->modules)->toHaveKey('admin.brand')
            ->and($result->artifact->modules)->toHaveKey('admin.product-mutation')
            ->and($result->artifact->modules['admin.brand']['controllers']['brand']['features'][0])->toMatchArray([
                'key' => 'admin.inventory.brand',
                'menu' => 'admin.inventory.product-mutation',
                'placement' => 'tab',
                'order' => 1,
            ])
            ->and($result->artifact->modules['admin.brand']['controllers']['brand']['menu'])->toBeNull()
            ->and($result->artifact->modules['admin.product-mutation']['controllers']['product_mutation']['menu']['key'])
            ->toBe('admin.inventory.product-mutation');
    });

    it('fails compile when a feature references an unknown menu key', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Supply/Controllers/SupplyController.php',
            'App\\Modules\\Admin\\Supply\\Controllers',
            'SupplyController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.inventory.supply', label: 'feature.supply', menu: 'admin.inventory.supply-missing', placement: 'tab')]
final class SupplyController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        expect(fn () => $compiler->compile())
            ->toThrow(MetadataCompileException::class, 'references unknown menu key [admin.inventory.supply-missing]');
    });

    it('fails compile when the same feature key appears in two modules', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Alpha/Controllers/AlphaController.php',
            'App\\Modules\\Admin\\Alpha\\Controllers',
            'AlphaController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'shared.feature', label: 'Alpha', placement: 'panel')]
final class AlphaController {}
PHP,
        );

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Beta/Controllers/BetaController.php',
            'App\\Modules\\Admin\\Beta\\Controllers',
            'BetaController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'shared.feature', label: 'Beta', placement: 'panel')]
final class BetaController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        expect(fn () => $compiler->compile())
            ->toThrow(MetadataCompileException::class, 'Duplicate feature key');
    });

    it('fails compile when a feature key equals a menu key in another module', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/MenuHost/Controllers/MenuHostController.php',
            'App\\Modules\\Admin\\MenuHost\\Controllers',
            'MenuHostController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Menu;

#[Menu(key: 'admin.menu.host', label: 'Host')]
final class MenuHostController {}
PHP,
        );

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/FeatureClash/Controllers/FeatureClashController.php',
            'App\\Modules\\Admin\\FeatureClash\\Controllers',
            'FeatureClashController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.menu.host', label: 'Clash', placement: 'panel')]
final class FeatureClashController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        expect(fn () => $compiler->compile())
            ->toThrow(MetadataCompileException::class, 'conflicts with menu key');
    });

    it('fails compile when a feature menu equals another feature key', function () {
        $modulesPath = metadataFixtureRoot().'/modules';

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Anchor/Controllers/AnchorController.php',
            'App\\Modules\\Admin\\Anchor\\Controllers',
            'AnchorController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.anchor.tab', label: 'Anchor', placement: 'panel')]
final class AnchorController {}
PHP,
        );

        writeMetadataFixtureClass(
            $modulesPath,
            'Admin/Pointer/Controllers/PointerController.php',
            'App\\Modules\\Admin\\Pointer\\Controllers',
            'PointerController',
            <<<'PHP'
use Laravarc\Core\Metadata\Attributes\Feature;

#[Feature(key: 'admin.pointer.child', label: 'Pointer', menu: 'admin.anchor.tab', placement: 'tab')]
final class PointerController {}
PHP,
        );

        $compiler = metadataCompiler(
            moduleRegistry: emptyModuleRegistry($modulesPath),
            store: new NullMetadataArtifactStore,
        );

        expect(fn () => $compiler->compile())
            ->toThrow(MetadataCompileException::class, 'clashes with feature key');
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
            ],
            [
                'concrete' => $namespace.'\\Services\\Queries\\UserQueryService',
                'contract' => 'App\\ArcMetadataTest\\Shared\\Admin\\User\\Contracts\\UserQueryServiceContract',
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
