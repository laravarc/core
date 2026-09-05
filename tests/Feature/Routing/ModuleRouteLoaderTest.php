<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Routing\ModuleRouteLoader;

describe('ModuleRouteLoader', function () {
    it('loads route files discovered in the module manifest', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/admin/user';

        mkdir($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS, 0777, true);
        touch($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS.'/2024_01_01_create_users_table.php');
        mkdir($moduleRoot.'/'.ModuleLayout::ROUTES, 0777, true);

        file_put_contents($moduleRoot.'/'.ModuleLayout::ROUTES.'/UserRoute.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/arc-module-route-fixture', static fn (): string => 'module-route-loaded');

PHP);

        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        app(ModuleRouteLoader::class)->load();

        $response = $this->get('/arc-module-route-fixture');

        expect($response->status())->toBe(200)
            ->and($response->getContent())->toBe('module-route-loaded');
    });

    it('skips loading when disabled via config', function () {
        config(['laravarc.load_module_routes' => false]);

        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/admin/catalog';

        mkdir($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS, 0777, true);
        touch($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS.'/2024_01_01_create_catalogs_table.php');
        mkdir($moduleRoot.'/'.ModuleLayout::ROUTES, 0777, true);

        file_put_contents($moduleRoot.'/'.ModuleLayout::ROUTES.'/CatalogRoute.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/arc-module-route-disabled', static fn (): string => 'should-not-load');

PHP);

        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        new ModuleRouteLoader(
            moduleRegistry: app(\Laravarc\Core\Discovery\ModuleRegistry::class),
            modulesPath: $modulesPath,
            enabled: false,
        )->load();

        $response = $this->get('/arc-module-route-disabled');

        expect($response->status())->toBe(404);
    });

    it('ignores modules without a route file', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/inventory/item';

        mkdir($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS, 0777, true);
        touch($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS.'/2024_01_01_create_items_table.php');

        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        expect(fn () => app(ModuleRouteLoader::class)->load())->not->toThrow(\Throwable::class);
    });

    it('wraps root module routes in Surfacer::group when a surface file exists', function () {
        if (! class_exists(\Laravarc\Surfacer\Surfacer::class)) {
            $this->markTestSkipped('laravarc/surfacer is not installed');
        }

        $modulesPath = config('laravarc.modules_path');
        $rootMeta = $modulesPath.'/Partner/'.\Laravarc\Core\Support\ModuleMetaDirectory::NAME;
        mkdir($rootMeta, 0777, true);

        file_put_contents($rootMeta.'/partner_surface.php', <<<'PHP'
<?php

declare(strict_types=1);

use Laravarc\Surfacer\Definition\SurfaceDefinition;

return (new SurfaceDefinition('partner'))
    ->prefix('partner-api')
    ->defaultVersion('v1')
    ->version('v1');
PHP);

        $moduleRoot = $modulesPath.'/Partner/Widget';
        mkdir($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS, 0777, true);
        touch($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS.'/2024_01_01_create_widgets_table.php');
        mkdir($moduleRoot.'/'.ModuleLayout::ROUTES, 0777, true);

        file_put_contents($moduleRoot.'/'.ModuleLayout::ROUTES.'/WidgetRoute.php', <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/widgets-surface', static fn (): string => 'surface-wrapped');

PHP);

        config([
            'surfacer.definitions_path' => $modulesPath.'/*/'.\Laravarc\Core\Support\ModuleMetaDirectory::NAME,
            'surfacer.cache.enabled' => false,
        ]);

        // Rebuild Surfacer repository with the new definitions_path
        app()->forgetInstance(\Laravarc\Surfacer\Registry\CachedSurfaceRepository::class);
        app()->forgetInstance(\Laravarc\Surfacer\Contracts\SurfaceRepository::class);
        app()->forgetInstance(\Laravarc\Surfacer\Surfacer::class);

        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        app()->forgetInstance(ModuleRouteLoader::class);
        app(ModuleRouteLoader::class)->load();

        $this->get('/partner-api/v1/widgets-surface')->assertOk()->assertSee('surface-wrapped');
        $this->get('/widgets-surface')->assertNotFound();
    });
});

describe('ModuleViewLoader', function () {
    it('registers module Views/ as a Blade namespace matching the module key', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/admin/report';

        mkdir($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS, 0777, true);
        touch($moduleRoot.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS.'/2024_01_01_create_reports_table.php');
        mkdir($moduleRoot.'/'.ModuleLayout::VIEWS, 0777, true);
        file_put_contents($moduleRoot.'/'.ModuleLayout::VIEWS.'/index.blade.php', 'report-index-ok');

        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        app(\Laravarc\Core\Routing\ModuleViewLoader::class)->load();

        expect(view()->exists('admin.report::index'))->toBeTrue()
            ->and(view('admin.report::index')->render())->toBe('report-index-ok');
    });
});
