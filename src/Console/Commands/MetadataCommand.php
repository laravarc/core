<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\Commands\Concerns\InteractsWithArcCommandOptions;
use Laravarc\Core\Contracts\MetadataCompiler;
use Throwable;

final class MetadataCommand extends Command
{
    use InteractsWithArcCommandOptions;

    protected $signature = 'laravarc:metadata
                            {action : compile}
                            {--module= : Compile metadata for a single module path}
                            {--force : Force the operation}
                            {--dry-run : Preview without writing metadata artifact}';

    protected $description = 'Compile Laravarc metadata artifacts';

    /** @var list<string> */
    protected $aliases = ['larc:metadata'];

    public function handle(MetadataCompiler $metadataCompiler): int
    {
        $action = (string) $this->argument('action');

        if ($action !== 'compile') {
            $this->error('Supported actions: compile.');

            return self::FAILURE;
        }

        try {
            $modulePath = $this->option('module');
            $modulePath = is_string($modulePath) && $modulePath !== '' ? $modulePath : null;

            $result = $metadataCompiler->compile(
                dryRun: $this->isDryRun(),
                modulePath: $modulePath,
            );

            if ($this->isDryRun()) {
                $this->info(sprintf(
                    'Dry run: would compile metadata for %d module(s).',
                    $result->moduleCount,
                ));
            } else {
                $this->info(sprintf(
                    'Metadata compiled for %d module(s).',
                    $result->moduleCount,
                ));
            }

            $this->line(sprintf(
                'Menus: %d | Features: %d | Abilities: %d%s',
                $result->menuCount,
                $result->featureCount,
                $result->policyCount,
                $result->persisted ? '' : ' (not persisted)',
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
