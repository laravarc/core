<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Laravarc\Core\Commands\Generation\GenerationSummaryBuilder;
use Laravarc\Core\Commands\Generation\GenerationSummaryLine;
use Laravarc\Core\Commands\Support\PendingGenerationStore;
use Laravarc\Core\Commands\Services\ContractSyncService;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Generation\Exceptions\UnknownPresetException;
use Laravarc\Core\Generation\GenerationContextFactory;
use Laravarc\Core\Generation\GenerationRunResult;
use Laravarc\Core\Generation\ModuleGenerationPipeline;
use Laravarc\Core\Generation\ModulePresetRegistry;
use Laravarc\Core\Generation\Metadata\MetadataSelection;
use Laravarc\Core\Module\Exceptions\ModuleNotFoundException;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLifecycleGuard;
use Laravarc\Core\Presentation\PackageRequirementChecker;
use Laravarc\Core\Presentation\PresentationStackRegistry;
use Laravarc\Core\Schema\SchemaService;

final readonly class ModuleGenerationResult
{
    /**
     * @param  list<GenerationSummaryLine>  $summary
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $modulePath,
        public GenerationRunResult $runResult,
        public array $summary,
        public array $warnings,
    ) {}

    public function succeeded(): bool
    {
        return $this->runResult->succeeded();
    }
}

final class ModuleMaker
{
    public function __construct(
        private readonly ModuleLifecycleGuard $lifecycleGuard,
        private readonly SchemaService $schemaService,
        private readonly GenerationContextFactory $contextFactory,
        private readonly ModuleGenerationPipeline $pipeline,
        private readonly ModulePresetRegistry $presetRegistry,
        private readonly PresentationStackRegistry $stackRegistry,
        private readonly PackageRequirementChecker $packageChecker,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly GenerationSummaryBuilder $summaryBuilder,
        private readonly PendingGenerationStore $pendingGenerationStore,
        private readonly ContractSyncService $contractSync,
    ) {}

    public function make(ModuleIdentity $identity, ModuleGenerationOptions $options): ModuleGenerationResult
    {
        $this->lifecycleGuard->assertCanMake($identity, $options->refresh);

        $result = $this->runGeneration($identity, $options);

        if (! $options->dryRun && $result->runResult->succeeded() && $this->isMigrationOnlyResult($result)) {
            $this->pendingGenerationStore->store($identity, $options);
        }

        return $result;
    }

    public function continueAfterMigrate(ModuleIdentity $identity, ModuleGenerationOptions $options): ModuleGenerationResult
    {
        if (! $identity->existsOnFilesystem()) {
            throw new ModuleNotFoundException(sprintf(
                'Module not found at path "%s".',
                $identity->path,
            ));
        }

        $options = $this->pendingGenerationStore->mergeWithStored($identity, $options);

        $result = $this->runGeneration($identity, $options);

        if (! $options->dryRun && $result->runResult->succeeded() && ! $this->isMigrationOnlyResult($result)) {
            $this->pendingGenerationStore->clear($identity);
        }

        return $result;
    }

    private function runGeneration(ModuleIdentity $identity, ModuleGenerationOptions $options): ModuleGenerationResult
    {
        if (! $this->presetRegistry->exists($options->preset)) {
            throw new UnknownPresetException(sprintf(
                'Unknown preset [%s]. Valid presets: %s.',
                $options->preset,
                implode(', ', $this->presetRegistry->keys()),
            ));
        }

        $metadataSelection = $options->metadataSelection();

        if ($metadataSelection->isEmpty() && $this->presetRegistry->enablesMetadata($options->preset)) {
            $metadataSelection = MetadataSelection::fromPreset('default');
        }

        $preset = $this->presetRegistry->normalizePreset($options->preset);

        $stack = $this->stackRegistry->resolve($options->stack);
        $this->packageChecker->assertInstalled($stack);

        $tableName = $this->schemaService->resolveTableName($identity, $options->tableOverride);
        $tableExists = $this->schemaService->tableExists($tableName);
        $schemaSnapshot = $tableExists
            ? $this->schemaService->readSnapshot($identity, $options->tableOverride)
            : null;

        $context = $this->contextFactory->make(
            identity: $identity,
            schemaSnapshot: $schemaSnapshot,
            tableName: $tableName,
            connection: null,
            tableExists: $tableExists,
            preset: $preset,
            presentationStack: $options->stack,
            refresh: $options->refresh,
            only: $options->only,
            except: $options->except,
            selectedLocale: $options->locale,
            config: [
                'route_middleware' => config('laravarc.route_middleware', []),
            ],
            metadataSelection: $metadataSelection,
            withContractAttributes: $options->withContractAttributes,
            withExtension: $options->withExtension,
        );

        $runResult = $this->pipeline->run($context, $options->dryRun);

        if (! $options->dryRun && $runResult->succeeded()) {
            $this->moduleRegistry->refresh();

            if ($options->withContractAttributes) {
                $this->contractSync->sync($identity->path);
            }
        }

        $summary = $this->summaryBuilder->build(
            generators: GenerationSummaryBuilder::defaultGenerators(),
            context: $context,
            generatedGenerators: $runResult->generatedGenerators,
            failures: $runResult->failures,
            dryRun: $options->dryRun,
        );

        return new ModuleGenerationResult(
            modulePath: $identity->path,
            runResult: $runResult,
            summary: $summary,
            warnings: $runResult->warnings,
        );
    }

    private function isMigrationOnlyResult(ModuleGenerationResult $result): bool
    {
        return $result->runResult->generatedGenerators === ['migration'];
    }
}
