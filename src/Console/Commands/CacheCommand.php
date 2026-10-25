<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\Commands\Concerns\InteractsWithArcCommandOptions;
use Laravarc\Core\Contracts\MetadataCompiler;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Catalog\CatalogState;
use Laravarc\Core\Catalog\CatalogSynchronizer;
use Laravarc\Core\Support\CachePath;
use Throwable;

final class CacheCommand extends Command
{
    use InteractsWithArcCommandOptions;

    protected $signature = 'laravarc:cache
                            {action : build, refresh (deprecated alias) or clear}
                            {--force : Force the operation}
                            {--dry-run : Preview without rebuilding cache artifacts}
                            {--no-catalog-sync : Skip catalog DB sync after build when installed}';

    protected $description = 'Build, refresh or clear Laravarc cache artifacts (catalog sync respects LARAVARC_CATALOG_SYNC and --no-catalog-sync)';

    /** @var list<string> */
    protected $aliases = ['larc:cache'];

    public function handle(
        ModuleRegistry $moduleRegistry,
        MetadataCompiler $metadataCompiler,
        ExtensionManager $extensions,
    ): int {
        $action = (string) $this->argument('action');

        try {
            return match ($action) {
                'build' => $this->handleBuild($moduleRegistry, $metadataCompiler, $extensions, deprecated: false),
                'refresh' => $this->handleBuild($moduleRegistry, $metadataCompiler, $extensions, deprecated: true),
                'clear' => $this->handleClear($moduleRegistry, $extensions),
                default => $this->unsupportedAction($action),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function handleBuild(
        ModuleRegistry $moduleRegistry,
        MetadataCompiler $metadataCompiler,
        ExtensionManager $extensions,
        bool $deprecated,
    ): int {
        if ($deprecated) {
            $this->warn('laravarc:cache refresh is deprecated; use laravarc:cache build.');
        }

        $catalogState = app(CatalogState::class);
        $wouldSyncCatalog = $catalogState->isInstalled() && $this->shouldRunCatalogSync();

        if ($this->isDryRun()) {
            $this->info('Dry run: would rebuild module manifest, metadata and ability catalogs.');
            $this->line($wouldSyncCatalog
                ? 'Dry run: would sync catalog tables from metadata.'
                : 'Dry run: catalog DB sync would be skipped.');

            return self::SUCCESS;
        }

        $manifest = $moduleRegistry->refresh();
        $metadataResult = $metadataCompiler->compile();
        $extensions->dispatch(ExtensionHook::CacheRefresh, $manifest);

        $this->info(sprintf('Module manifest built with %d module(s).', count($manifest->all())));
        $this->line(sprintf(
            'Metadata compiled for %d module(s). Abilities: %d%s',
            $metadataResult->moduleCount,
            $metadataResult->policyCount,
            $metadataResult->persisted ? '' : ' (not persisted)',
        ));

        if ($wouldSyncCatalog) {
            $stats = app(CatalogSynchronizer::class)->sync();
            $this->line(sprintf(
                'Catalog sync: inserted=%d updated=%d orphaned=%d restored=%d',
                $stats['inserted'],
                $stats['updated'],
                $stats['orphaned'],
                $stats['restored'],
            ));
        } else {
            $this->line('Catalog DB sync skipped (catalog not installed or disabled).');
        }

        return self::SUCCESS;
    }

    private function handleClear(ModuleRegistry $moduleRegistry, ExtensionManager $extensions): int
    {
        if ($this->isDryRun()) {
            $this->info('Dry run: would clear module manifest and metadata caches.');

            return self::SUCCESS;
        }

        $moduleRegistry->clear();
        app(\Laravarc\Core\Contracts\MetadataArtifactStore::class)->clear();

        $abilityPath = CachePath::abilities();

        if (is_file($abilityPath)) {
            @unlink($abilityPath);
        }

        $extensions->dispatch(ExtensionHook::CacheClear);

        $this->info('Laravarc cache artifacts cleared.');

        return self::SUCCESS;
    }

    private function unsupportedAction(string $action): int
    {
        $this->error('Supported actions: build, refresh (deprecated), clear.');

        return self::FAILURE;
    }

    private function shouldRunCatalogSync(): bool
    {
        if ((bool) $this->option('no-catalog-sync')) {
            return false;
        }

        $env = strtolower((string) env('LARAVARC_CATALOG_SYNC', 'true'));

        return ! in_array($env, ['0', 'false', 'no', 'off'], true);
    }
}
