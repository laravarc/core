<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Contracts\ModuleManifestStore;
use Laravarc\Core\Discovery\ModuleManifestStoreFactory;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Discovery\ModuleScanner;
use Laravarc\Core\Discovery\ModuleServiceProviderLoader;
use Laravarc\Core\Discovery\ModuleServiceProviderResolver;

final class DiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManifestStoreFactory::class);

        $this->app->singleton(ModuleManifestStore::class, function ($app) {
            $factory = $app->make(ModuleManifestStoreFactory::class);

            return $factory->make(
                (string) config('laravarc.manifest_store', 'file'),
                (string) config('laravarc.manifest_file_path'),
                (string) config('laravarc.manifest_json_path'),
            );
        });

        $this->app->singleton(ModuleServiceProviderResolver::class);

        $this->app->singleton(ModuleScanner::class, function ($app) {
            return new ModuleScanner(
                moduleKeyResolver: $app->make(\Laravarc\Core\Contracts\ModuleKeyResolver::class),
                serviceProviderResolver: $app->make(ModuleServiceProviderResolver::class),
            );
        });

        $this->app->singleton(ModuleRegistry::class, function ($app) {
            return new ModuleRegistry(
                scanner: $app->make(ModuleScanner::class),
                store: $app->make(ModuleManifestStore::class),
                modulesPath: (string) config('laravarc.modules_path'),
                moduleNamespace: (string) config('laravarc.module_namespace'),
            );
        });

        $this->app->singleton(ModuleServiceProviderLoader::class, function ($app) {
            return new ModuleServiceProviderLoader(
                moduleRegistry: $app->make(ModuleRegistry::class),
                app: $app,
                enabled: (bool) config('laravarc.load_module_service_providers', true),
            );
        });
    }

    public function boot(): void
    {
        // After app config is ready; before ExtensionManager is first resolved.
        $this->app->make(ModuleServiceProviderLoader::class)->load();
    }
}
