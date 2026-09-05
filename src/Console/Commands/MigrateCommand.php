<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\Commands\Concerns\InteractsWithArcCommandOptions;
use Laravarc\Core\Commands\Generation\GenerationSummaryPrinter;
use Laravarc\Core\Commands\Services\ModuleGenerationOptions;
use Laravarc\Core\Commands\Services\ModuleMigrator;
use Laravarc\Core\Commands\Support\CommaSeparatedOptionParser;
use Throwable;

final class MigrateCommand extends Command
{
    use InteractsWithArcCommandOptions;

    protected $signature = 'laravarc:migrate
                            {--module= : Module path relative to modules_path}
                            {--path= : Module path relative to modules_path for scoped migrations}
                            {--preset= : Generation preset for post-migrate generation}
                            {--stack= : Presentation stack for post-migrate generation}
                            {--table= : Table name override}
                            {--only= : Comma-separated generator list}
                            {--except= : Comma-separated generator exclusions}
                            {--metadata= : Metadata attributes or presets (comma-separated: menu, feature, policy, public, default)}
                            {--force : Force migrations in production}
                            {--dry-run : Preview without running migrations or generating files}';

    protected $description = 'Run module migrations and continue generation when needed';

    /** @var list<string> */
    protected $aliases = ['larc:migrate'];

    public function handle(
        ModuleMigrator $moduleMigrator,
        GenerationSummaryPrinter $summaryPrinter,
    ): int {
        try {
            if ($this->isDryRun()) {
                $this->info('Dry run: migrations and generation would be evaluated for the selected module scope.');
            }

            $results = $moduleMigrator->migrate(
                module: $this->option('module'),
                path: $this->option('path'),
                force: $this->isForce(),
                dryRun: $this->isDryRun(),
                generationOptions: new ModuleGenerationOptions(
                    preset: (string) ($this->option('preset') ?: config('laravarc.default_preset', 'crud')),
                    stack: (string) ($this->option('stack') ?: config('laravarc.default_stack', 'api')),
                    tableOverride: $this->option('table') ?: null,
                    only: CommaSeparatedOptionParser::parse($this->option('only')),
                    except: CommaSeparatedOptionParser::parse($this->option('except')),
                    refresh: false,
                    force: $this->isForce(),
                    dryRun: $this->isDryRun(),
                    metadata: $this->metadataOptionValue(),
                ),
            );

            foreach ($results as $result) {
                foreach ($result->warnings as $warning) {
                    $this->warn($warning);
                }

                $summaryPrinter->print($this->output, $result->modulePath, $result->summary);

                if (! $result->succeeded()) {
                    foreach ($result->runResult->failures as $failure) {
                        $this->error(sprintf('[%s] %s', $failure->generator, $failure->message));
                    }

                    return self::FAILURE;
                }
            }

            if ($results === []) {
                $this->info('Migrations completed. No modules required generation.');
            } else {
                $this->info('Migrations completed and module generation continued.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
