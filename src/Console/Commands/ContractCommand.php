<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\Commands\Services\ContractSyncService;
use Throwable;

final class ContractCommand extends Command
{
    protected $signature = 'laravarc:contract
                            {action : sync}
                            {module? : Optional module path to sync a single module}';

    protected $description = 'Sync Laravarc service contracts from Command/Query attributes';

    /** @var list<string> */
    protected $aliases = ['larc:contract'];

    public function handle(ContractSyncService $contractSync): int
    {
        $action = (string) $this->argument('action');

        if ($action !== 'sync') {
            $this->error('Supported actions: sync.');

            return self::FAILURE;
        }

        try {
            $module = $this->argument('module');
            $module = is_string($module) && $module !== '' ? $module : null;
            $files = $contractSync->sync($module);

            if ($files === []) {
                $this->info('No contract files were generated or updated.');

                return self::SUCCESS;
            }

            foreach ($files as $file) {
                $this->line($file);
            }

            $this->info(sprintf('Contract sync complete (%d file change(s)).', count($files)));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
