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

    protected $description = 'Run module database seeders';

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

            foreach ($results as $result) {
                if ($this->isDryRun()) {
                    $this->line(sprintf(
                        'Dry run: would seed module [%s] using %s',
                        $result->modulePath,
                        implode(', ', $result->seeders),
                    ));
                } else {
                    $this->info(sprintf(
                        'Seeded module [%s] using %s',
                        $result->modulePath,
                        implode(', ', $result->seeders),
                    ));
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
