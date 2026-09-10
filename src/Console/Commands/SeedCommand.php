<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\Commands\Concerns\InteractsWithArcCommandOptions;
use Laravarc\Core\Commands\Services\ModuleSeeder;
use Throwable;

final class SeedCommand extends Command
{
    use InteractsWithArcCommandOptions;

    protected $signature = 'laravarc:seed
                            {--module= : Module path relative to modules_path}
                            {--force : Force seeding in production}
                            {--dry-run : Preview without running seeders}';

    protected $description = 'Run module database seeders (optional #[SeedPriority]: larger int runs first / z-index)';

    /** @var list<string> */
    protected $aliases = ['larc:seed'];

    public function handle(ModuleSeeder $moduleSeeder): int
    {
        try {
            $results = $moduleSeeder->seed(
                modulePath: $this->option('module'),
                force: $this->isForce(),
                dryRun: $this->isDryRun(),
            );

            if ($this->isDryRun()) {
                $this->line('Dry run: would seed in final order:');
            }

            foreach ($results as $index => $result) {
                $line = sprintf(
                    '%d. %s [%s]',
                    $index + 1,
                    $result->seederClass,
                    $result->modulePath,
                );

                if ($this->isDryRun()) {
                    $this->line($line);
                } else {
                    $this->info(sprintf('Seeded %s', $line));
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
