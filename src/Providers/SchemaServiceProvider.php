<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Contracts\SchemaReader;
use Laravarc\Core\Schema\CachingSchemaReader;
use Laravarc\Core\Schema\ColumnTypeMapper;
use Laravarc\Core\Schema\DatabaseSchemaReader;
use Laravarc\Core\Schema\SchemaIntrospectorFactory;
use Laravarc\Core\Schema\SchemaService;
use Laravarc\Core\Schema\SchemaSnapshotCache;
use Laravarc\Core\Schema\TableNameResolver;

final class SchemaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchemaIntrospectorFactory::class);
        $this->app->singleton(ColumnTypeMapper::class);
        $this->app->singleton(TableNameResolver::class);
        $this->app->singleton(SchemaSnapshotCache::class, function () {
            return new SchemaSnapshotCache((string) config('laravarc.schema_cache_path'));
        });

        $this->app->singleton(SchemaReader::class, function ($app) {
            $reader = new DatabaseSchemaReader(
                introspectorFactory: $app->make(SchemaIntrospectorFactory::class),
                columnTypeMapper: $app->make(ColumnTypeMapper::class),
            );

            if ((bool) config('laravarc.schema_cache_enabled', false)) {
                return new CachingSchemaReader(
                    inner: $reader,
                    cache: $app->make(SchemaSnapshotCache::class),
                    databaseManager: $app->make('db'),
                );
            }

            return $reader;
        });

        $this->app->singleton(SchemaService::class);
    }
}
