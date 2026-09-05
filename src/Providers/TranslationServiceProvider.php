<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Database\ModuleMigrationLoader;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\I18n\ModuleTranslationLoader;
use Laravarc\Core\Support\CorePathResolver;

final class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleTranslationLoader::class, function ($app) {
            return new ModuleTranslationLoader(
                moduleRegistry: $app->make(ModuleRegistry::class),
                modulesPath: (string) config('laravarc.modules_path'),
                sharedPath: CorePathResolver::resolve((string) config('laravarc.shared_path', app_path('Shared'))),
                loadModuleTranslations: (bool) config('laravarc.load_module_translations', true),
                loadSharedTranslations: (bool) config('laravarc.load_shared_translations', true),
            );
        });

        $this->app->singleton(ModuleMigrationLoader::class, function ($app) {
            return new ModuleMigrationLoader(
                moduleRegistry: $app->make(ModuleRegistry::class),
                modulesPath: (string) config('laravarc.modules_path'),
                enabled: (bool) config('laravarc.load_module_migrations', true),
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(ModuleTranslationLoader::class)
            ->load($this->app->make(Translator::class));

        foreach ($this->app->make(ModuleMigrationLoader::class)->paths() as $path) {
            $this->loadMigrationsFrom($path);
        }
    }
}
