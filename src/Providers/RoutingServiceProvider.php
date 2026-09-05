<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Routing\ModuleRouteLoader;
use Laravarc\Core\Routing\ModuleViewLoader;

final class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRouteLoader::class, function ($app) {
            return new ModuleRouteLoader(
                moduleRegistry: $app->make(\Laravarc\Core\Discovery\ModuleRegistry::class),
                modulesPath: (string) config('laravarc.modules_path'),
                enabled: (bool) config('laravarc.load_module_routes', true),
            );
        });

        $this->app->singleton(ModuleViewLoader::class, function ($app) {
            return new ModuleViewLoader(
                moduleRegistry: $app->make(\Laravarc\Core\Discovery\ModuleRegistry::class),
                modulesPath: (string) config('laravarc.modules_path'),
                enabled: (bool) config('laravarc.load_module_views', true),
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(ModuleViewLoader::class)->load();

        if ($this->app->routesAreCached()) {
            return;
        }

        $this->app->make(ModuleRouteLoader::class)->load();
    }
}
