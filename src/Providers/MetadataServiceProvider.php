<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Authorization\AbilityCatalogReader;
use Laravarc\Core\Authorization\AbilityCatalogWriter;
use Laravarc\Core\Authorization\PolicyConventionResolver;
use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Contracts\MetadataArtifactStore;
use Laravarc\Core\Contracts\MetadataCompiler;
use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Http\Controllers\MetadataController;
use Laravarc\Core\Metadata\ContractPathResolver;
use Laravarc\Core\Metadata\CoreMetadataCompiler;
use Laravarc\Core\Metadata\ListenerMetadataReader;
use Laravarc\Core\Metadata\MetadataArtifactStoreFactory;
use Laravarc\Core\Metadata\MetadataService;
use Laravarc\Core\Metadata\Stores\FileMetadataArtifactStore;
use Laravarc\Core\Support\CachePath;
use Laravarc\Core\Metadata\ModuleClassDiscoverer;
use Laravarc\Core\Metadata\ReflectionMetadataReader;
use Laravarc\Core\Metadata\ServiceMetadataReader;

final class MetadataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetadataArtifactStoreFactory::class);

        $this->app->singleton(MetadataArtifactStore::class, function () {
            return new FileMetadataArtifactStore(CachePath::metadata());
        });

        $this->app->singleton(AbilityCatalogReader::class, function () {
            return new AbilityCatalogReader(CachePath::abilities());
        });
        $this->app->singleton(AbilityCatalog::class, fn ($app) => $app->make(AbilityCatalogReader::class));
        $this->app->singleton(AbilityCatalogWriter::class, function () {
            return new AbilityCatalogWriter(CachePath::abilities());
        });

        $this->app->singleton(PolicyConventionResolver::class);
        $this->app->singleton(ModuleClassDiscoverer::class);
        $this->app->singleton(ReflectionMetadataReader::class);
        $this->app->singleton(ContractPathResolver::class, function () {
            return new ContractPathResolver(
                sharedPath: (string) config('laravarc.shared_path', app_path('Shared')),
            );
        });
        $this->app->singleton(ServiceMetadataReader::class);
        $this->app->singleton(ListenerMetadataReader::class);

        $this->app->singleton(MetadataCompiler::class, function ($app) {
            return new CoreMetadataCompiler(
                moduleRegistry: $app->make(\Laravarc\Core\Discovery\ModuleRegistry::class),
                reader: $app->make(ReflectionMetadataReader::class),
                serviceReader: $app->make(ServiceMetadataReader::class),
                listenerReader: $app->make(ListenerMetadataReader::class),
                store: $app->make(MetadataArtifactStore::class),
                extensions: $app->make(ExtensionManager::class),
                abilityWriter: $app->make(AbilityCatalogWriter::class),
            );
        });

        $this->app->singleton(MetadataService::class);
    }

    public function boot(): void
    {
        if (! (bool) config('laravarc.expose_metadata_endpoint', false)) {
            return;
        }

        $router = $this->app->make('router');
        $path = (string) config('laravarc.metadata_endpoint_path', '/laravarc/metadata');
        /** @var list<string> $middleware */
        $middleware = config('laravarc.metadata_endpoint_middleware', ['auth']);

        $router->get($path, MetadataController::class)->middleware($middleware);
    }
}
