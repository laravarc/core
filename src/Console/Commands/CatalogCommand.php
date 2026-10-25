<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravarc\Core\Catalog\CatalogState;
use Laravarc\Core\Catalog\CatalogSynchronizer;
use Laravarc\Core\Commands\Concerns\InteractsWithArcCommandOptions;
use Throwable;

final class CatalogCommand extends Command
{
    use InteractsWithArcCommandOptions;

    protected $signature = 'laravarc:catalog
                            {action : install, uninstall, reset, or status}
                            {--force : Force destructive actions in production}';

    protected $description = 'Install, uninstall, reset, or inspect the Laravarc catalog database';

    /** @var list<string> */
    protected $aliases = ['larc:catalog'];

    public function handle(CatalogState $state, CatalogSynchronizer $synchronizer): int
    {
        $action = (string) $this->argument('action');

        try {
            return match ($action) {
                'install' => $this->handleInstall($state, $synchronizer),
                'uninstall' => $this->handleUninstall($state),
                'reset' => $this->handleReset($state, $synchronizer),
                'status' => $this->handleStatus($state),
                default => $this->unsupportedAction($action),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function handleInstall(CatalogState $state, CatalogSynchronizer $synchronizer): int
    {
        $this->call('migrate', [
            '--path' => $this->catalogMigrationPath(),
            '--realpath' => true,
            '--force' => true,
        ]);

        $state->markInstalled();

        $stats = $synchronizer->sync();
        $this->printSyncStats($stats);
        $this->info('Laravarc catalog installed.');

        return self::SUCCESS;
    }

    private function handleUninstall(CatalogState $state): int
    {
        if ($this->requiresProductionForce()) {
            return self::FAILURE;
        }

        $this->warn('Uninstalling removes labels, custom menus, and translations stored in laravarc catalog tables.');

        Schema::dropIfExists('larc_feature_translations');
        Schema::dropIfExists('larc_features');
        Schema::dropIfExists('larc_menu_translations');
        Schema::dropIfExists('larc_menus');

        $state->markUninstalled();
        $this->info('Laravarc catalog uninstalled.');

        return self::SUCCESS;
    }

    private function handleReset(CatalogState $state, CatalogSynchronizer $synchronizer): int
    {
        if (! $state->isInstalled()) {
            throw new \RuntimeException('Catalog is not installed.');
        }

        if ($this->requiresProductionForce()) {
            return self::FAILURE;
        }

        DB::transaction(function (): void {
            DB::table('larc_feature_translations')->delete();
            DB::table('larc_menu_translations')->delete();
            DB::table('larc_features')->delete();
            DB::table('larc_menus')->delete();
        });

        $stats = $synchronizer->sync();
        $this->printSyncStats($stats);
        $state->bumpRevision();
        $this->info('Laravarc catalog reset from metadata.');

        return self::SUCCESS;
    }

    private function handleStatus(CatalogState $state): int
    {
        $snapshot = $state->read();

        $this->line(sprintf('installed: %s', $snapshot['installed'] ? 'yes' : 'no'));
        $this->line(sprintf('revision: %d', $snapshot['revision']));
        $this->line(sprintf('state file: %s', $state->path()));

        return self::SUCCESS;
    }

    /**
     * @param  array{inserted: int, updated: int, orphaned: int, restored: int, warnings: list<string>}  $stats
     */
    private function printSyncStats(array $stats): void
    {
        $this->line(sprintf(
            'Catalog sync: inserted=%d updated=%d orphaned=%d restored=%d',
            $stats['inserted'],
            $stats['updated'],
            $stats['orphaned'],
            $stats['restored'],
        ));

        foreach ($stats['warnings'] as $warning) {
            $this->warn($warning);
        }
    }

    private function catalogMigrationPath(): string
    {
        return dirname(__DIR__, 3).'/database/migrations/2026_10_25_000001_create_laravarc_catalog_tables.php';
    }

    private function requiresProductionForce(): bool
    {
        if ($this->laravel->environment('production') && ! $this->isForce()) {
            $this->error('Production environment requires --force.');

            return true;
        }

        return false;
    }

    private function unsupportedAction(string $action): int
    {
        $this->error('Supported actions: install, uninstall, reset, status.');

        return self::FAILURE;
    }
}
