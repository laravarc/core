<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Catalog\CatalogManager;
use Laravarc\Core\Catalog\CatalogManifestBuilder;
use Laravarc\Core\Catalog\CatalogMetadataCollector;
use Laravarc\Core\Catalog\CatalogMetadataSurfaceIndex;
use Laravarc\Core\Catalog\CatalogState;
use Laravarc\Core\Catalog\CatalogSynchronizer;
use Laravarc\Core\Catalog\CatalogUserVisibility;
use Laravarc\Core\Catalog\CatalogWriter;
use Laravarc\Core\Catalog\DatabaseCatalogReader;
use Laravarc\Core\Catalog\MetadataCatalogReader;
use Laravarc\Core\Catalog\SwitchingCatalogReader;
use Laravarc\Core\Contracts\CatalogReader;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CatalogState::class);
        $this->app->singleton(CatalogMetadataCollector::class);
        $this->app->singleton(CatalogSynchronizer::class);
        $this->app->singleton(CatalogManifestBuilder::class);
        $this->app->singleton(CatalogWriter::class);
        $this->app->singleton(MetadataCatalogReader::class);
        $this->app->singleton(DatabaseCatalogReader::class);
        $this->app->singleton(CatalogMetadataSurfaceIndex::class);
        $this->app->singleton(CatalogUserVisibility::class);

        $this->app->singleton(CatalogReader::class, SwitchingCatalogReader::class);
        $this->app->singleton(CatalogManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
    }
}
