<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Commands\Generation\GenerationSummaryBuilder;
use Laravarc\Core\Commands\Generation\GenerationSummaryPrinter;
use Laravarc\Core\Commands\Generation\ModuleRelocatePreviewPrinter;
use Laravarc\Core\Commands\Services\ContractSyncService;
use Laravarc\Core\Commands\Services\FqcnNamespaceReplacer;
use Laravarc\Core\Commands\Services\ModuleMaker;
use Laravarc\Core\Commands\Services\ModuleMigrator;
use Laravarc\Core\Commands\Services\ModuleRelocator;
use Laravarc\Core\Commands\Services\ModuleRemover;
use Laravarc\Core\Commands\Services\ModuleSeeder;
use Laravarc\Core\Commands\Support\ModuleGenerationState;
use Laravarc\Core\Commands\Support\ModuleIdentityResolver;
use Laravarc\Core\Commands\Support\PendingGenerationStore;
use Laravarc\Core\Console\Commands\AiRuleCommand;
use Laravarc\Core\Console\Commands\CacheCommand;
use Laravarc\Core\Console\Commands\ContractCommand;
use Laravarc\Core\Console\Commands\InstallCommand;
use Laravarc\Core\Console\Commands\MetadataCommand;
use Laravarc\Core\Console\Commands\MigrateCommand;
use Laravarc\Core\Console\Commands\ModuleMakeCommand;
use Laravarc\Core\Console\Commands\SeedCommand;
use Laravarc\Core\Contracts\MetadataCompiler;
use Laravarc\Core\Module\ModuleLifecycleGuard;

final class CommandServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleIdentityResolver::class, function () {
            return new ModuleIdentityResolver(
                modulesPath: (string) config('laravarc.modules_path'),
                moduleNamespace: (string) config('laravarc.module_namespace'),
            );
        });

        $this->app->singleton(PendingGenerationStore::class);
        $this->app->singleton(ModuleGenerationState::class);
        $this->app->singleton(GenerationSummaryBuilder::class);
        $this->app->singleton(GenerationSummaryPrinter::class);
        $this->app->singleton(ModuleLifecycleGuard::class);
        $this->app->singleton(ModuleMaker::class);
        $this->app->singleton(ModuleRemover::class);
        $this->app->singleton(ContractSyncService::class);
        $this->app->singleton(FqcnNamespaceReplacer::class);
        $this->app->singleton(ModuleRelocatePreviewPrinter::class);

        $this->app->singleton(ModuleRelocator::class, function ($app) {
            return new ModuleRelocator(
                identityResolver: $app->make(ModuleIdentityResolver::class),
                moduleRegistry: $app->make(\Laravarc\Core\Discovery\ModuleRegistry::class),
                namespaceReplacer: $app->make(FqcnNamespaceReplacer::class),
                metadataCompiler: $app->make(MetadataCompiler::class),
                filesystem: $app->make('files'),
                modulesPath: (string) config('laravarc.modules_path'),
            );
        });

        $this->app->singleton(ModuleMigrator::class, function ($app) {
            return new ModuleMigrator(
                identityResolver: $app->make(ModuleIdentityResolver::class),
                generationState: $app->make(ModuleGenerationState::class),
                moduleMaker: $app->make(ModuleMaker::class),
                filesystem: $app->make('files'),
                artisan: $app->make(\Illuminate\Contracts\Console\Kernel::class),
                modulesPath: (string) config('laravarc.modules_path'),
            );
        });

        $this->app->singleton(ModuleSeeder::class, function ($app) {
            return new ModuleSeeder(
                identityResolver: $app->make(ModuleIdentityResolver::class),
                moduleRegistry: $app->make(\Laravarc\Core\Discovery\ModuleRegistry::class),
                artisan: $app->make(\Illuminate\Contracts\Console\Kernel::class),
                modulesPath: (string) config('laravarc.modules_path'),
            );
        });
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ModuleMakeCommand::class,
            MigrateCommand::class,
            SeedCommand::class,
            MetadataCommand::class,
            CacheCommand::class,
            AiRuleCommand::class,
            ContractCommand::class,
            InstallCommand::class,
        ]);
    }
}
