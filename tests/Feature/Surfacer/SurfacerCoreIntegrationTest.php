<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravarc\Core\Convention\DefaultLayerResolver;
use Laravarc\Core\Convention\DefaultModuleKeyResolver;
use Laravarc\Core\Convention\DefaultRequestResolver;
use Laravarc\Core\Generation\GenerationContextFactory;
use Laravarc\Core\Generation\GeneratorRegistry;
use Laravarc\Core\Generation\Generators\RouteGenerator;
use Laravarc\Core\Generation\ModuleGenerationPipeline;
use Laravarc\Core\Generation\ModulePresetRegistry;
use Laravarc\Core\Generation\StubResolver;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Presentation\PresentationGenerationContextFactory;
use Laravarc\Core\Routing\ModuleRouteLoader;
use Laravarc\Core\Schema\ColumnTypeMapper;
use Laravarc\Core\Schema\DatabaseSchemaReader;
use Laravarc\Core\Support\ModuleMetaDirectory;
use Laravarc\Core\Surfacer\RootSurfaceLocator;
use Laravarc\Core\Surfacer\SurfacerCoreExtension;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospector;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospectorFactory;
use Laravarc\Surfacer\Contracts\SurfaceRepository;
use Laravarc\Surfacer\Registry\CachedSurfaceRepository;
use Laravarc\Surfacer\Surfacer;

/**
 * End-to-end: Surfacer disk convention + Core loader wrap + slim route generation.
 * Catches soft-dependency wiring gaps that unit tests on each side can miss.
 */
describe('Surfacer ↔ Core end-to-end integration', function () {
    beforeEach(function () {
        if (! class_exists(Surfacer::class)) {
            $this->markTestSkipped('laravarc/surfacer is not installed');
        }
    });

    it('loads routes through Surfacer::group and generates slim stubs under a surfaced root', function () {
        $modulesPath = config('laravarc.modules_path');

        // --- Root surface (convention B.2) ---
        $metaDir = $modulesPath.'/Portal/'.ModuleMetaDirectory::NAME;
        mkdir($metaDir, 0777, true);

        file_put_contents($metaDir.'/portal_surface.php', <<<'PHP'
<?php

declare(strict_types=1);

use Laravarc\Surfacer\Definition\SurfaceDefinition;

return (new SurfaceDefinition('portal'))
    ->prefix('portal-api')
    ->middleware(['api'])
    ->defaultVersion('v1')
    ->version('v1');
PHP);

        // --- Existing hand-written module route (no local prefix — Surface owns boundary) ---
        $widgetRoot = $modulesPath.'/Portal/Widget';
        mkdir($widgetRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS, 0777, true);
        touch($widgetRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS.'/2024_01_01_create_widgets_table.php');
        mkdir($widgetRoot.'/'.ModuleLayout::ROUTES, 0777, true);

        file_put_contents($widgetRoot.'/'.ModuleLayout::ROUTES.'/WidgetRoute.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/widgets-e2e', static fn (): string => 'e2e-wrapped');

PHP);

        config([
            'surfacer.definitions_path' => $modulesPath.'/*/'.ModuleMetaDirectory::NAME,
            'surfacer.cache.enabled' => false,
            'laravarc.extensions' => array_values(array_unique([
                ...config('laravarc.extensions', []),
                SurfacerCoreExtension::class,
            ])),
        ]);

        app()->forgetInstance(CachedSurfaceRepository::class);
        app()->forgetInstance(SurfaceRepository::class);
        app()->forgetInstance(Surfacer::class);
        app()->forgetInstance(ModuleRouteLoader::class);

        expect(app(SurfaceRepository::class)->has('portal'))->toBeTrue()
            ->and((new RootSurfaceLocator)->hasSurface($modulesPath, 'Portal'))->toBeTrue();

        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        // Artisan may re-bootstrap routing after the manifest refresh; clear so this
        // assertion measures only the Surfacer-aware ModuleRouteLoader pass.
        app('router')->setRoutes(new \Illuminate\Routing\RouteCollection);

        app()->forgetInstance(ModuleRouteLoader::class);
        app(ModuleRouteLoader::class)->load();

        // Soft-wiring check: Surface boundary actually applied
        $this->get('/portal-api/v1/widgets-e2e')->assertOk()->assertSee('e2e-wrapped');
        $this->get('/portal-api/widgets-e2e')->assertOk()->assertSee('e2e-wrapped');

        $widgetUris = collect(Route::getRoutes())
            ->map(static fn ($route) => $route->uri())
            ->filter(static fn ($uri) => str_contains((string) $uri, 'widgets-e2e'))
            ->values()
            ->all();

        expect($widgetUris)->toBe([
            'portal-api/v1/widgets-e2e',
            'portal-api/widgets-e2e',
        ]);

        // --- Generate a NEW module under the same surfaced root → slim stub ---
        $ticketIdentity = ModuleIdentity::fromPath('portal/ticket', $modulesPath, 'App\\Modules');
        mkdir($ticketIdentity->rootPath, 0777, true);

        $schema = (new DatabaseSchemaReader(
            introspectorFactory: new FakeSchemaIntrospectorFactory(new FakeSchemaIntrospector(
                columns: [
                    'tickets' => [
                        [
                            'name' => 'id',
                            'type_name' => 'integer',
                            'type' => 'integer',
                            'nullable' => false,
                            'default' => null,
                            'auto_increment' => true,
                        ],
                        [
                            'name' => 'title',
                            'type_name' => 'varchar',
                            'type' => 'varchar(255)',
                            'nullable' => false,
                            'default' => null,
                            'auto_increment' => false,
                        ],
                    ],
                ],
                indexes: [
                    'tickets' => [
                        ['columns' => ['id'], 'primary' => true, 'unique' => true],
                    ],
                ],
            )),
            columnTypeMapper: new ColumnTypeMapper,
        ))->read('tickets');

        $stubsPath = realpath(__DIR__.'/../../../stubs') ?: __DIR__.'/../../../stubs';
        $moduleKeyResolver = new DefaultModuleKeyResolver;
        $layerResolver = new DefaultLayerResolver;

        $context = (new GenerationContextFactory(
            moduleKeyResolver: $moduleKeyResolver,
            layerResolver: $layerResolver,
            requestResolver: new DefaultRequestResolver,
            presentationContextFactory: new PresentationGenerationContextFactory(
                $moduleKeyResolver,
                $layerResolver,
            ),
            generatorRegistry: new GeneratorRegistry(new ModulePresetRegistry),
            stubResolver: new StubResolver($stubsPath),
        ))->make(
            identity: $ticketIdentity,
            schemaSnapshot: $schema,
            tableName: 'tickets',
            connection: null,
            tableExists: true,
            preset: 'crud',
            presentationStack: 'api',
            refresh: false,
            only: ['route'],
            except: [],
            selectedLocale: null,
            config: ['route_middleware' => ['api', 'auth:sanctum']],
        );

        $result = (new ModuleGenerationPipeline(
            new Filesystem,
            [new RouteGenerator],
        ))->run($context);

        expect($result->succeeded())->toBeTrue();

        $routeFile = $ticketIdentity->rootPath.'/Routes/TicketRoute.php';
        expect(file_exists($routeFile))->toBeTrue();

        $contents = file_get_contents($routeFile);

        // Slim: Surface owns domain/api/middleware; module owns plural resource path.
        expect($contents)->toContain('Route::controller(TicketController::class)->group')
            ->and($contents)->not->toContain('Route::middleware([')
            ->and($contents)->toContain("Route::prefix('tickets')")
            ->and($contents)->toContain("->name('portal.ticket.index')");
    });
});
