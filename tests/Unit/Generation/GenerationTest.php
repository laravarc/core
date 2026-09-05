<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Laravarc\Core\Convention\DefaultLayerResolver;
use Laravarc\Core\Convention\DefaultModuleKeyResolver;
use Laravarc\Core\Convention\DefaultRequestResolver;
use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Extensions\ExtensionPackageChecker;
use Laravarc\Core\Generation\GenerationContextFactory;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Generation\GeneratorRegistry;
use Laravarc\Core\Generation\Metadata\MetadataSelection;
use Laravarc\Core\Generation\ModuleGenerationPipeline;
use Laravarc\Core\Generation\ModuleGeneratorCatalog;
use Laravarc\Core\Generation\ModulePresetRegistry;
use Laravarc\Core\Generation\StubRenderer;
use Laravarc\Core\Generation\StubResolver;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Presentation\PresentationGenerationContextFactory;
use Laravarc\Core\Schema\ColumnSnapshot;
use Laravarc\Core\Schema\DatabaseSchemaReader;
use Laravarc\Core\Schema\SchemaSnapshot;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospector;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospectorFactory;
use Laravarc\Eventer\EventerExtension;

function generationModuleIdentity(string $suffix = ''): ModuleIdentity
{
    $modulesPath = sys_get_temp_dir().'/arc-generation-'.uniqid('', true).$suffix;
    mkdir($modulesPath.'/Admin/User', 0777, true);

    return ModuleIdentity::fromPath('admin/user', $modulesPath, 'App\\Modules');
}

function generationSchemaSnapshot(): SchemaSnapshot
{
    $reader = new DatabaseSchemaReader(
        introspectorFactory: new FakeSchemaIntrospectorFactory(generationPostsSchemaFixtures()),
        columnTypeMapper: new Laravarc\Core\Schema\ColumnTypeMapper,
    );

    return $reader->read('users');
}

function generationPostsSchemaFixtures(): FakeSchemaIntrospector
{
    return new FakeSchemaIntrospector(
        columns: [
            'users' => [
                [
                    'name' => 'id',
                    'type_name' => 'integer',
                    'type' => 'integer',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => true,
                ],
                [
                    'name' => 'email',
                    'type_name' => 'varchar',
                    'type' => 'varchar(255)',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'is_active',
                    'type_name' => 'boolean',
                    'type' => 'boolean',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'balance',
                    'type_name' => 'decimal',
                    'type' => 'decimal(10,2)',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                    'precision' => 10,
                    'scale' => 2,
                ],
                [
                    'name' => 'meta',
                    'type_name' => 'json',
                    'type' => 'json',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'deleted_at',
                    'type_name' => 'datetime',
                    'type' => 'datetime',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'created_at',
                    'type_name' => 'datetime',
                    'type' => 'datetime',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'updated_at',
                    'type_name' => 'datetime',
                    'type' => 'datetime',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
            ],
        ],
        indexes: [
            'users' => [
                ['columns' => ['id'], 'primary' => true, 'unique' => true],
            ],
        ],
    );
}

function generationStubsPath(): string
{
    return realpath(__DIR__.'/../../../stubs') ?: __DIR__.'/../../../stubs';
}

function generationContextFactory(): GenerationContextFactory
{
    return new GenerationContextFactory(
        moduleKeyResolver: new DefaultModuleKeyResolver,
        layerResolver: new DefaultLayerResolver,
        requestResolver: new DefaultRequestResolver,
        presentationContextFactory: new PresentationGenerationContextFactory(
            new DefaultModuleKeyResolver,
            new DefaultLayerResolver,
        ),
        generatorRegistry: new GeneratorRegistry(new ModulePresetRegistry),
        stubResolver: new StubResolver(generationStubsPath()),
    );
}

describe('ModulePresetRegistry', function () {
    it('defines built-in presets with crud as default catalog', function () {
        $registry = new ModulePresetRegistry;

        expect($registry->generatorsFor('crud'))->toBe([
            GeneratorName::MIGRATION,
            GeneratorName::MODEL,
            GeneratorName::REPOSITORY,
            GeneratorName::SERVICE,
            GeneratorName::CONTROLLER,
            GeneratorName::FORM_REQUEST,
            GeneratorName::POLICY,
            GeneratorName::VIEW,
            GeneratorName::ROUTE,
            GeneratorName::SERVICE_PROVIDER,
        ])->and($registry->generatorsFor('crud+metadata'))
            ->toBe($registry->generatorsFor('crud'))
            ->and($registry->enablesMetadata('crud+metadata'))->toBeTrue()
            ->and($registry->normalizePreset('crud+metadata'))->toBe('crud')
            ->and($registry->generatorsFor('crud+resource'))
            ->toContain(GeneratorName::RESOURCE)
            ->and($registry->generatorsFor('full'))
            ->toBe(GeneratorName::all());
    });

    it('merges bridge presets from config', function () {
        $registry = new ModulePresetRegistry([
            'datatable' => [GeneratorName::MODEL, GeneratorName::CONTROLLER],
        ]);

        expect($registry->generatorsFor('datatable'))
            ->toBe([GeneratorName::MODEL, GeneratorName::CONTROLLER]);
    });
});

describe('GeneratorRegistry', function () {
    beforeEach(function () {
        $this->registry = new GeneratorRegistry(new ModulePresetRegistry);
    });

    it('returns migration-only generators when table is absent', function () {
        $resolution = $this->registry->resolve('crud', 'api', true, false, false, null, [], []);

        expect($resolution->generators)->toBe([
            GeneratorName::MIGRATION,
            GeneratorName::SERVICE_PROVIDER,
        ]);
    });

    it('removes migration when table exists on default crud preset', function () {
        $resolution = $this->registry->resolve('crud', 'api', false, false, true, null, [], []);

        expect($resolution->generators)->not->toContain(GeneratorName::MIGRATION);
    });

    it('removes migration on refresh', function () {
        $resolution = $this->registry->resolve('full', 'api', false, true, true, null, [], []);

        expect($resolution->generators)->not->toContain(GeneratorName::MIGRATION);
    });

    it('removes resource when stack is blade', function () {
        $resolution = $this->registry->resolve('crud+resource', 'blade', false, false, true, null, [], []);

        expect($resolution->generators)->not->toContain(GeneratorName::RESOURCE)
            ->and($resolution->generators)->toContain(GeneratorName::VIEW);
    });

    it('removes view when stack is api', function () {
        $resolution = $this->registry->resolve('crud', 'api', false, false, true, null, [], []);

        expect($resolution->generators)->not->toContain(GeneratorName::VIEW);
    });

    it('prefers only filters and warns when except is also provided', function () {
        $resolution = $this->registry->resolve(
            'crud',
            'api',
            false,
            false,
            true,
            null,
            ['model', 'controller'],
            ['route'],
        );

        expect($resolution->generators)->toBe(['model', 'controller'])
            ->and($resolution->warnings)->toContain('The --except option was ignored because --only takes precedence.');
    });

    it('rejects unknown generator names', function () {
        expect(fn () => $this->registry->resolve('crud', 'api', false, false, true, null, ['unknown'], []))
            ->toThrow(Laravarc\Core\Generation\Exceptions\UnknownGeneratorException::class);
    });
});

describe('StubResolver', function () {
    it('prefers application override over published and built-in stubs', function () {
        $root = sys_get_temp_dir().'/arc-stubs-'.uniqid('', true);
        $override = $root.'/override';
        $published = $root.'/published';
        $builtin = $root.'/builtin';

        mkdir($override, 0777, true);
        mkdir($published, 0777, true);
        mkdir($builtin, 0777, true);

        file_put_contents($override.'/model.stub', 'override');
        file_put_contents($published.'/model.stub', 'published');
        file_put_contents($builtin.'/model.stub', 'builtin');

        $resolver = new StubResolver($builtin, $published, $override);

        expect($resolver->resolve('model.stub'))->toBe($override.'/model.stub');
    });
});

describe('StubRenderer', function () {
    it('replaces stub placeholders', function () {
        $rendered = StubRenderer::render('namespace {{ namespace }};', [
            'namespace' => 'App\\Modules\\User',
        ]);

        expect($rendered)->toBe('namespace App\\Modules\\User;');
    });
});

describe('GenerationContextFactory', function () {
    it('builds immutable data-only generation context', function () {
        $identity = generationModuleIdentity();
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud',
            presentationStack: 'api',
            refresh: false,
            only: [],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
        );

        expect($context->modulePath)->toBe('Admin/User')
            ->and($context->moduleKey)->toBe('admin.user')
            ->and($context->moduleNamespace)->toBe('App\\Modules\\Admin\\User')
            ->and($context->moduleName)->toBe('User')
            ->and($context->filesystemRoot)->toBe($identity->rootPath)
            ->and($context->selectedPreset)->toBe('crud')
            ->and($context->selectedGenerators)->not->toContain(GeneratorName::MIGRATION)
            ->and($context->controllerReturns['index'])->toBe('UserResource::collection($users)')
            ->and($context->resolvedClasses['model']['className'])
            ->toBe('App\\Modules\\Admin\\User\\Models\\User');
    });
});

describe('ModuleGenerationPipeline', function () {
    it('generates core crud files for api stack', function () {
        $identity = generationModuleIdentity('-pipeline');
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud+resource',
            presentationStack: 'api',
            refresh: false,
            only: [],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
        );

        $result = new ModuleGenerationPipeline(new Filesystem, ModuleGeneratorCatalog::builtIn())->run($context);

        expect($result->succeeded())->toBeTrue()
            ->and($result->writtenFiles)->toContain('Models/User.php')
            ->and($result->writtenFiles)->toContain('Controllers/UserController.php')
            ->and($result->writtenFiles)->toContain('Resources/UserResource.php')
            ->and($result->writtenFiles)->toContain('Routes/UserRoute.php')
            ->and(file_get_contents($identity->rootPath.'/Models/User.php'))
            ->toContain('class User extends Model')
            ->and(file_get_contents($identity->rootPath.'/Controllers/UserController.php'))
            ->toContain('UserResource::collection($users)')
            ->and(file_get_contents($identity->rootPath.'/Controllers/UserController.php'))
            ->not->toContain('function create(')
            ->and(file_get_contents($identity->rootPath.'/Routes/UserRoute.php'))
            ->toContain('Route::controller(UserController::class)->group')
            ->toContain("Route::prefix('users')")
            ->toContain("->name('admin.user.index')")
            ->not->toContain('apiResource')
            ->not->toContain('->names(');
    });

    it('generates blade controller create/edit and explicit named routes', function () {
        $identity = generationModuleIdentity('-blade-pipeline');
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud',
            presentationStack: 'blade',
            refresh: false,
            only: [],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['web']],
        );

        $result = new ModuleGenerationPipeline(new Filesystem, ModuleGeneratorCatalog::builtIn())->run($context);
        $controller = file_get_contents($identity->rootPath.'/Controllers/UserController.php');
        $routes = file_get_contents($identity->rootPath.'/Routes/UserRoute.php');

        expect($result->succeeded())->toBeTrue()
            ->and($result->writtenFiles)->toContain('Views/.gitkeep')
            ->and($controller)->toContain("view('admin.user::index'")
            ->and($controller)->toContain('function create()')
            ->and($controller)->toContain("view('admin.user::create')")
            ->and($controller)->toContain('function edit(mixed $id)')
            ->and($controller)->toContain("view('admin.user::edit'")
            ->and($routes)->toContain('Route::controller(UserController::class)->group')
            ->and($routes)->toContain("Route::prefix('users')")
            ->and($routes)->toContain("Route::get('/create', 'create')->name('admin.user.create')")
            ->and($routes)->toContain("Route::get('/{id}/edit', 'edit')->name('admin.user.edit')")
            ->and($routes)->not->toContain('Route::resource')
            ->and($routes)->not->toContain('->names(');
    });

    it('emits metadata attributes when default metadata selection is enabled', function () {
        $identity = generationModuleIdentity('-metadata');
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud',
            presentationStack: 'api',
            refresh: false,
            only: [],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
            metadataSelection: MetadataSelection::fromPreset('default'),
        );

        $result = new ModuleGenerationPipeline(new Filesystem, ModuleGeneratorCatalog::builtIn())->run($context);

        expect($result->succeeded())->toBeTrue()
            ->and(file_get_contents($identity->rootPath.'/Controllers/UserController.php'))
            ->toContain('Menu')
            ->toContain("key: 'admin.user.index'")
            ->toContain('Feature')
            ->toContain("placement: 'tab'")
            ->and(file_get_contents($identity->rootPath.'/Controllers/UserController.php'))
            ->toContain('Policy')
            ->toContain("ability: 'viewAny'")
            ->and(file_get_contents($identity->rootPath.'/Policies/UserPolicy.php'))
            ->not->toContain('Metadata\\Attributes\\Policy');
    });

    it('emits only selected metadata attributes', function () {
        $identity = generationModuleIdentity('-metadata-partial');
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud',
            presentationStack: 'api',
            refresh: false,
            only: [],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
            metadataSelection: MetadataSelection::fromAttributes([
                \Laravarc\Core\Generation\Metadata\MetadataAttribute::Public,
                \Laravarc\Core\Generation\Metadata\MetadataAttribute::Menu,
            ]),
        );

        $result = new ModuleGenerationPipeline(new Filesystem, ModuleGeneratorCatalog::builtIn())->run($context);
        $controller = file_get_contents($identity->rootPath.'/Controllers/UserController.php');

        expect($result->succeeded())->toBeTrue()
            ->and($controller)->toContain('PublicAccess as Public')
            ->and($controller)->toContain('Menu')
            ->and($controller)->not->toContain('Feature')
            ->and($controller)->not->toContain('#[Policy');
    });

    it('generates plain Shared events and native event() dispatch when Eventer is absent', function () {
        $sharedRelative = 'ArcGenShared'.bin2hex(random_bytes(3));
        $sharedPath = app_path($sharedRelative);
        mkdir($sharedPath, 0777, true);
        config(['laravarc.shared_path' => $sharedRelative]);

        $identity = generationModuleIdentity('-events-no-eventer');
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud+events',
            presentationStack: 'api',
            refresh: false,
            only: [],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
        );

        $result = new ModuleGenerationPipeline(new Filesystem, ModuleGeneratorCatalog::builtIn())->run($context);

        $createdEventPath = $sharedPath.'/Admin/User/Events/UserCreatedEvent.php';
        $deletedEventPath = $sharedPath.'/Admin/User/Events/UserDeletedEvent.php';
        $createdEvent = file_get_contents($createdEventPath);
        $deletedEvent = file_get_contents($deletedEventPath);
        $commandService = file_get_contents($identity->rootPath.'/Services/Commands/UserCommandService.php');
        $listener = file_get_contents($identity->rootPath.'/Listeners/LogUserCreatedListener.php');
        $eventsNamespace = 'App\\'.\Illuminate\Support\Str::studly($sharedRelative).'\\Admin\\User\\Events';

        expect($result->succeeded())->toBeTrue()
            ->and($context->withEvents)->toBeTrue()
            ->and($createdEvent)->toContain('namespace '.$eventsNamespace.';')
            ->and($createdEvent)->toContain('final class UserCreatedEvent')
            ->and($createdEvent)->toContain('public readonly int $userId')
            ->and($createdEvent)->not->toContain('implements ')
            ->and($createdEvent)->not->toContain('EventContract')
            ->and($createdEvent)->not->toContain('DomainEvent')
            ->and($createdEvent)->not->toContain('HasDefaultEventContract')
            ->and($createdEvent)->not->toContain('static function dispatch')
            ->and($createdEvent)->not->toContain('Dispatchable')
            ->and($deletedEvent)->toContain('final class UserDeletedEvent')
            ->and($commandService)->toContain('event(new UserCreatedEvent((int) $user->getKey()));')
            ->and($commandService)->toContain('event(new UserDeletedEvent((int) $id));')
            ->and($commandService)->not->toContain('Eventer::')
            ->and($commandService)->not->toContain('EventDispatcher')
            ->and($listener)->toContain('use Laravarc\\Core\\Metadata\\Attributes\\ListenTo;')
            ->and($listener)->toContain('#[ListenTo(UserCreatedEvent::class)]');
    });

    it('generates Eventer::dispatch when EventerExtension is registered, with identical event files', function () {
        $container = new Container;
        $container->singleton(EventerExtension::class, fn (): EventerExtension => new EventerExtension);
        $extensions = new ExtensionManager(
            container: $container,
            packageChecker: new ExtensionPackageChecker,
        );
        $extensions->configure([EventerExtension::class]);

        $sharedRelative = 'ArcGenShared'.bin2hex(random_bytes(3));
        $sharedPath = app_path($sharedRelative);
        mkdir($sharedPath, 0777, true);
        config(['laravarc.shared_path' => $sharedRelative]);

        $identityWith = generationModuleIdentity('-events-with-eventer');
        $contextWith = generationContextFactory()->make(
            identity: $identityWith,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud+events',
            presentationStack: 'api',
            refresh: false,
            only: [],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
        );
        $result = new ModuleGenerationPipeline(
            new Filesystem,
            ModuleGeneratorCatalog::builtIn($extensions),
            $extensions,
        )->run($contextWith);

        $createdWith = file_get_contents($sharedPath.'/Admin/User/Events/UserCreatedEvent.php');
        $deletedWith = file_get_contents($sharedPath.'/Admin/User/Events/UserDeletedEvent.php');
        $commandService = file_get_contents($identityWith->rootPath.'/Services/Commands/UserCommandService.php');
        $listener = file_get_contents($identityWith->rootPath.'/Listeners/LogUserCreatedListener.php');

        $sharedRelativeWithout = 'ArcGenShared'.bin2hex(random_bytes(3));
        $sharedPathWithout = app_path($sharedRelativeWithout);
        mkdir($sharedPathWithout, 0777, true);
        config(['laravarc.shared_path' => $sharedRelativeWithout]);
        $identityWithout = generationModuleIdentity('-events-compare');
        $contextWithout = generationContextFactory()->make(
            identity: $identityWithout,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud+events',
            presentationStack: 'api',
            refresh: false,
            only: ['event'],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
        );
        new ModuleGenerationPipeline(new Filesystem, ModuleGeneratorCatalog::builtIn())->run($contextWithout);
        $createdWithout = file_get_contents($sharedPathWithout.'/Admin/User/Events/UserCreatedEvent.php');
        $deletedWithout = file_get_contents($sharedPathWithout.'/Admin/User/Events/UserDeletedEvent.php');

        $stripSharedNs = static function (string $contents): string {
            return (string) preg_replace(
                '/namespace App\\\\ArcGenShared[A-Za-z0-9]+\\\\Admin\\\\User\\\\Events;/',
                'namespace App\\Shared\\Admin\\User\\Events;',
                $contents,
            );
        };

        expect($result->succeeded())->toBeTrue()
            ->and($commandService)->toContain('use Laravarc\\Eventer\\Facades\\Eventer;')
            ->and($commandService)->toContain("Eventer::dispatch(new UserCreatedEvent((int) \$user->getKey()));")
            ->and($commandService)->toContain("Eventer::dispatch(new UserDeletedEvent((int) \$id));")
            ->and($commandService)->not->toContain('event(')
            ->and($commandService)->not->toContain("Eventer::channel('internal')")
            ->and($listener)->toContain('use Laravarc\\Core\\Metadata\\Attributes\\ListenTo;')
            ->and($listener)->toContain('#[ListenTo(UserCreatedEvent::class)]')
            ->and($stripSharedNs($createdWith))->toBe($stripSharedNs($createdWithout))
            ->and($stripSharedNs($deletedWith))->toBe($stripSharedNs($deletedWithout))
            ->and(file_get_contents($identityWith->rootPath.'/Repositories/UserRepository.php'))->toContain('public function findMany(array $ids)')
            ->and(file_get_contents($identityWith->rootPath.'/Services/Queries/UserQueryService.php'))->toContain('public function findManyUser(array $ids)');
    });
    it('generates model casts for non-string fillable columns and deleted_at', function () {
        $identity = generationModuleIdentity('-casts');
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud',
            presentationStack: 'api',
            refresh: false,
            only: ['model'],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
        );

        $result = new ModuleGenerationPipeline(new Filesystem, ModuleGeneratorCatalog::builtIn())->run($context);
        $model = file_get_contents($identity->rootPath.'/Models/User.php');

        expect($result->succeeded())->toBeTrue()
            ->and($model)->toContain("'email',")
            ->and($model)->not->toContain("'email' =>")
            ->and($model)->toContain("'is_active' => 'boolean',")
            ->and($model)->toContain("'balance' => 'decimal:10:2',")
            ->and($model)->toContain("'meta' => 'array',")
            ->and($model)->toContain("'deleted_at' => 'datetime',");
    });

    it('generates only migration in migration-only mode', function () {
        $identity = generationModuleIdentity('-migration-only');
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: null,
            tableName: 'users',
            connection: null,
            tableExists: false,
            preset: 'crud',
            presentationStack: 'api',
            refresh: false,
            only: [],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
        );

        $result = new ModuleGenerationPipeline(new Filesystem, ModuleGeneratorCatalog::builtIn())->run($context);

        expect($result->writtenFiles)->toHaveCount(3)
            ->and($result->writtenFiles[0])->toMatch('/Database\\/Migrations\\/\\d{4}_\\d{2}_\\d{2}_\\d{6}_create_users_table\\.php/')
            ->and($result->writtenFiles[1])->toBe('Database/Seeders/.gitkeep')
            ->and($result->writtenFiles[2])->toBe('Providers/UserServiceProvider.php');
    });

    it('keeps partial files when a generator fails', function () {
        $identity = generationModuleIdentity('-partial');
        $context = generationContextFactory()->make(
            identity: $identity,
            schemaSnapshot: generationSchemaSnapshot(),
            tableName: 'users',
            connection: null,
            tableExists: true,
            preset: 'crud',
            presentationStack: 'api',
            refresh: false,
            only: ['model'],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api']],
        );

        $failing = new class implements Laravarc\Core\Contracts\ModuleGenerator
        {
            public function name(): string
            {
                return GeneratorName::MODEL;
            }

            public function supports(Laravarc\Core\Generation\GenerationContext $context): bool
            {
                return in_array($this->name(), $context->selectedGenerators, true);
            }

            public function generate(Laravarc\Core\Generation\GenerationContext $context): array
            {
                throw new RuntimeException('Generator failed');
            }
        };

        $pipeline = new ModuleGenerationPipeline(new Filesystem, [$failing]);

        $result = $pipeline->run($context);

        expect($result->succeeded())->toBeFalse()
            ->and($result->failures)->toHaveCount(1)
            ->and($result->failures[0]->generator)->toBe(GeneratorName::MODEL);
    });
});

describe('CoreServiceProvider generation bindings', function () {
    it('registers generation services', function () {
        expect($this->app->make(GenerationContextFactory::class))->toBeInstanceOf(GenerationContextFactory::class)
            ->and($this->app->make(ModuleGenerationPipeline::class))->toBeInstanceOf(ModuleGenerationPipeline::class)
            ->and(config('laravarc.default_preset'))->toBe('crud');
    });
});