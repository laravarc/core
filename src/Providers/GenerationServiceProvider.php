<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Extensions\ExtensionPackageChecker;
use Laravarc\Core\Generation\GenerationContextFactory;
use Laravarc\Core\Generation\GeneratorRegistry;
use Laravarc\Core\Generation\ModuleGenerationPipeline;
use Laravarc\Core\Generation\ModuleGeneratorCatalog;
use Laravarc\Core\Generation\ModulePresetRegistry;
use Laravarc\Core\Generation\StubResolver;

final class GenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExtensionPackageChecker::class);

        $this->app->singleton(ExtensionManager::class, function ($app) {
            $manager = new ExtensionManager(
                container: $app,
                packageChecker: $app->make(ExtensionPackageChecker::class),
            );

            /** @var list<class-string> $extensions */
            $extensions = config('laravarc.extensions', []);
            $manager->configure(is_array($extensions) ? $extensions : []);

            return $manager;
        });

        $this->app->singleton(ModulePresetRegistry::class, function ($app) {
            /** @var array<string, list<string>> $customPresets */
            $customPresets = config('laravarc.presets', []);

            return new ModulePresetRegistry(
                customPresets: $customPresets,
                extensions: $app->make(ExtensionManager::class),
            );
        });

        $this->app->singleton(GeneratorRegistry::class);

        $this->app->singleton(StubResolver::class, function () {
            $stubs = config('laravarc.stubs', []);

            return new StubResolver(
                builtinPath: dirname(__DIR__, 2).'/stubs',
                publishedPath: $stubs['published_path'] ?? null,
                overridePath: $stubs['override_path'] ?? null,
            );
        });

        $this->app->singleton(GenerationContextFactory::class);

        $this->app->singleton(ModuleGenerationPipeline::class, function ($app) {
            $extensions = $app->make(ExtensionManager::class);

            return new ModuleGenerationPipeline(
                filesystem: $app->make('files'),
                generators: ModuleGeneratorCatalog::builtIn($extensions),
                extensions: $extensions,
            );
        });
    }
}
