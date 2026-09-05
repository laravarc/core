<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests;

use Laravarc\Core\Providers\CoreServiceProvider;
use Laravarc\Core\Contracts\SchemaReader;
use Laravarc\Core\Schema\ColumnTypeMapper;
use Laravarc\Core\Schema\DatabaseSchemaReader;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospector;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospectorFactory;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $providers = [
            CoreServiceProvider::class,
        ];

        if (class_exists(\Laravarc\Surfacer\Providers\SurfacerServiceProvider::class)) {
            $providers[] = \Laravarc\Surfacer\Providers\SurfacerServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $modulesPath = $this->fixtureModulesPath();
        mkdir($modulesPath, 0777, true);
        $sharedPath = $modulesPath.'/Shared';
        mkdir($sharedPath, 0777, true);

        $app['config']->set('laravarc.modules_path', $modulesPath);
        $app['config']->set('laravarc.shared_path', $sharedPath);
        $app['config']->set('laravarc.module_namespace', 'App\\Modules');
        $app['config']->set('laravarc.manifest_file_path', $modulesPath.'/manifest.php');
        $app['config']->set('laravarc.manifest_json_path', $modulesPath.'/manifest.json');
        $app['config']->set('laravarc.schema_cache_enabled', false);
        $app['config']->set('laravarc.metadata_file_path', $modulesPath.'/metadata.php');
        $app['config']->set('laravarc.expose_metadata_endpoint', true);
        $app['config']->set('laravarc.metadata_endpoint_path', '/laravarc/metadata');
        $app['config']->set('laravarc.metadata_endpoint_middleware', []);

        $app->singleton(SchemaReader::class, function () {
            return new DatabaseSchemaReader(
                introspectorFactory: new FakeSchemaIntrospectorFactory(new FakeSchemaIntrospector),
                columnTypeMapper: new ColumnTypeMapper,
            );
        });
    }

    protected function fixtureModulesPath(): string
    {
        return sys_get_temp_dir().'/arc-module-tests-'.uniqid('', true);
    }
}
