<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\Commands\Concerns\InteractsWithArcCommandOptions;
use Laravarc\Core\Contracts\MetadataCompiler;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\ExtensionManager;
use Throwable;

final class CacheCommand extends Command
{
    use InteractsWithArcCommandOptions;

    protected $signature = 'laravarc:cache
                            {action : refresh or clear}
                            {--force : Force the operation}
                            {--dry-run : Preview without rebuilding cache artifacts}';

    protected $description = 'Refresh or clear Laravarc cache artifacts';

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
                'refresh' => $this->handleRefresh($moduleRegistry, $metadataCompiler, $extensions),
                'clear' => $this->handleClear($moduleRegistry, $extensions),
                default => $this->unsupportedAction($action),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function handleRefresh(
        ModuleRegistry $moduleRegistry,
        MetadataCompiler $metadataCompiler,
        ExtensionManager $extensions,
    ): int {
        if ($this->isDryRun()) {
            $this->info('Dry run: would rebuild module manifest and metadata caches.');

            return self::SUCCESS;
        }

        $manifest = $moduleRegistry->refresh();
        $metadataResult = $metadataCompiler->compile();
        $extensions->dispatch(ExtensionHook::CacheRefresh, $manifest);

        $this->info(sprintf('Module manifest refreshed with %d module(s).', count($manifest->all())));
        $this->line(sprintf(
            'Metadata compiled for %d module(s). Abilities: %d%s',
            $metadataResult->moduleCount,
            $metadataResult->policyCount,
            $metadataResult->persisted ? '' : ' (not persisted)',
        ));

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
        $extensions->dispatch(ExtensionHook::CacheClear);

        $this->info('Laravarc cache artifacts cleared.');

        return self::SUCCESS;
    }

    private function unsupportedAction(string $action): int
    {
        $this->error('Supported actions: refresh, clear.');

        return self::FAILURE;
    }
}
