<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\Commands\Concerns\InteractsWithArcCommandOptions;
use Laravarc\Core\Commands\Services\ModuleSeeder;
use Laravarc\Core\Commands\Services\ModuleSeedResult;
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
            if ($this->isDryRun()) {
                $results = $moduleSeeder->seed(
                    modulePath: $this->option('module'),
                    force: $this->isForce(),
                    dryRun: true,
                );

                $this->line('Dry run: would seed in final order:');

                foreach ($results as $index => $result) {
                    $this->line($this->formatSeederLine($result, $index));
                }

                return self::SUCCESS;
            }

            // Stream one line per seeder as it finishes (do not buffer until batch end).
            $moduleSeeder->seed(
                modulePath: $this->option('module'),
                force: $this->isForce(),
                dryRun: false,
                onProgress: function (ModuleSeedResult $result, int $index): void {
                    $this->info(sprintf('Seeded %s', $this->formatSeederLine($result, $index)));
                },
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function formatSeederLine(ModuleSeedResult $result, int $index): string
    {
        return sprintf(
            '%d. %s [%s]',
            $index + 1,
            $result->seederClass,
            $result->modulePath,
        );
    }
}
