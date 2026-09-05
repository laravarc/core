<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\Commands\Concerns\ConfirmsModuleRelocation;
use Laravarc\Core\Commands\Concerns\ConfirmsModuleRemoval;
use Laravarc\Core\Commands\Concerns\InteractsWithArcCommandOptions;
use Laravarc\Core\Commands\Generation\ModuleRelocatePreviewPrinter;
use Laravarc\Core\Commands\Generation\GenerationSummaryPrinter;
use Laravarc\Core\Commands\Services\ModuleGenerationOptions;
use Laravarc\Core\Commands\Services\ModuleMaker;
use Laravarc\Core\Commands\Support\CommaSeparatedOptionParser;
use Laravarc\Core\Commands\Services\ModuleRemover;
use Laravarc\Core\Commands\Services\ModuleRelocator;
use Laravarc\Core\Commands\Support\ModuleIdentityResolver;
use Throwable;

final class ModuleMakeCommand extends Command
{
    use ConfirmsModuleRemoval;
    use ConfirmsModuleRelocation;
    use InteractsWithArcCommandOptions;

    protected $signature = 'laravarc:module
                            {action : make, remove, or migrate}
                            {path : Module path relative to modules_path}
                            {target? : Target module path for migrate action}
                            {--preset= : Generation preset}
                            {--stack= : Presentation stack key}
                            {--table= : Table name override}
                            {--only= : Comma-separated generator list}
                            {--except= : Comma-separated generator exclusions}
                            {--refresh : Regenerate an existing module}
                            {--metadata= : Metadata attributes or presets (comma-separated: menu, feature, policy, public, default)}
                            {--contract : Emit CommandContract/QueryContract attributes on generated service methods}
                            {--with-extension : Scaffold Extensions/{Basename}CoreExtension.php and register via module ServiceProvider}
                            {--force : Force the operation}
                            {--dry-run : Preview without writing files}';

    protected $description = 'Create, remove, or relocate a Laravarc feature module';

    /** @var list<string> */
    protected $aliases = ['larc:module'];

    public function handle(
        ModuleMaker $moduleMaker,
        ModuleRemover $moduleRemover,
        ModuleRelocator $moduleRelocator,
        ModuleRelocatePreviewPrinter $previewPrinter,
        ModuleIdentityResolver $identityResolver,
        GenerationSummaryPrinter $summaryPrinter,
    ): int {
        $action = (string) $this->argument('action');

        if ($action === 'remove') {
            return $this->handleRemove($moduleRemover, $identityResolver);
        }

        if ($action === 'migrate') {
            return $this->handleMigrate($moduleRelocator, $previewPrinter);
        }

        if ($action !== 'make') {
            $this->error('Supported actions: make, remove, migrate.');

            return self::FAILURE;
        }

        try {
            $identity = $identityResolver->resolve((string) $this->argument('path'));
            $options = $this->generationOptions();

            $result = $moduleMaker->make($identity, $options);

            foreach ($result->warnings as $warning) {
                $this->warn($warning);
            }

            $summaryPrinter->print($this->output, $identity->path, $result->summary);

            if ($this->isDryRun()) {
                $this->info('Dry run complete. No files were written.');
            } elseif ($result->succeeded()) {
                $this->info('Module generated successfully.');
            } else {
                foreach ($result->runResult->failures as $failure) {
                    $this->error(sprintf('[%s] %s', $failure->generator, $failure->message));
                }

                $this->warn('Some generators failed. Re-run with --refresh after fixing the issue.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function generationOptions(): ModuleGenerationOptions
    {
        return new ModuleGenerationOptions(
            preset: (string) ($this->option('preset') ?: config('laravarc.default_preset', 'crud')),
            stack: (string) ($this->option('stack') ?: config('laravarc.default_stack', 'api')),
            tableOverride: $this->option('table') ?: null,
            only: CommaSeparatedOptionParser::parse($this->option('only')),
            except: CommaSeparatedOptionParser::parse($this->option('except')),
            refresh: (bool) $this->option('refresh'),
            force: $this->isForce(),
            dryRun: $this->isDryRun(),
            metadata: $this->metadataOptionValue(),
            withContractAttributes: (bool) $this->option('contract'),
            withExtension: (bool) $this->option('with-extension'),
        );
    }

    private function handleRemove(ModuleRemover $moduleRemover, ModuleIdentityResolver $identityResolver): int
    {
        try {
            $path = (string) $this->argument('path');
            $identity = $identityResolver->resolve($path);

            if ($this->isDryRun()) {
                $this->info(sprintf('Dry run: would remove module at [%s].', $identity->rootPath));

                return self::SUCCESS;
            }

            if (! $this->confirmModuleRemoval($path)) {
                $this->warn('Module removal cancelled.');

                return self::FAILURE;
            }

            $moduleRemover->remove($identity, dryRun: false);
            $this->info(sprintf('Module [%s] removed successfully.', $identity->path));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function handleMigrate(ModuleRelocator $moduleRelocator, ModuleRelocatePreviewPrinter $previewPrinter): int
    {
        try {
            $sourcePath = (string) $this->argument('path');
            $targetPath = (string) $this->argument('target');

            if ($targetPath === '') {
                $this->error('The target module path is required for migrate action.');

                return self::FAILURE;
            }

            $plan = $moduleRelocator->plan($sourcePath, $targetPath);
            $previewPrinter->print($this->output, $plan);

            if ($this->isDryRun()) {
                $this->info('Dry run complete. No files were changed.');

                return self::SUCCESS;
            }

            if (! $this->confirmModuleRelocation($sourcePath, $targetPath)) {
                $this->warn('Module relocation cancelled.');

                return self::FAILURE;
            }

            $moduleRelocator->execute($plan);

            $this->info(sprintf(
                'Module relocated from [%s] to [%s] successfully.',
                $plan->source->path,
                $plan->target->path,
            ));
            $this->printRelocationWarnings();

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
