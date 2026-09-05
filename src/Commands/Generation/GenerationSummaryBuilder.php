<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Generation;

use Laravarc\Core\Contracts\ModuleGenerator;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GenerationFailure;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Generation\ModuleGeneratorCatalog;

final class GenerationSummaryBuilder
{
    /**
     * @param  list<ModuleGenerator>  $generators
     * @param  list<string>  $generatedGenerators
     * @param  list<GenerationFailure>  $failures
     * @return list<GenerationSummaryLine>
     */
    public function build(
        array $generators,
        GenerationContext $context,
        array $generatedGenerators,
        array $failures,
        bool $dryRun,
    ): array {
        $failedNames = array_map(
            static fn (GenerationFailure $failure): string => $failure->generator,
            $failures,
        );

        $lines = [];

        foreach ($generators as $generator) {
            $name = $generator->name();
            $label = GeneratorLabel::for($name);

            if (in_array($name, $failedNames, true)) {
                $lines[] = new GenerationSummaryLine('failed', $label);

                continue;
            }

            if (in_array($name, $generatedGenerators, true)) {
                $lines[] = new GenerationSummaryLine('generated', $dryRun ? $label.' (dry-run)' : $label);

                continue;
            }

            if (! in_array($name, $context->selectedGenerators, true)) {
                $lines[] = new GenerationSummaryLine('skipped', $label, $this->reasonForDeselected($name, $context));

                continue;
            }

            if (! $generator->supports($context)) {
                $lines[] = new GenerationSummaryLine('skipped', $label, $this->reasonForUnsupported($name, $context));

                continue;
            }

            $lines[] = new GenerationSummaryLine('skipped', $label, 'preset');
        }

        return $lines;
    }

    private function reasonForDeselected(string $generator, GenerationContext $context): string
    {
        if ($generator === GeneratorName::MIGRATION && $context->refresh) {
            return 'refresh';
        }

        if ($generator === GeneratorName::MIGRATION && $context->tableExists) {
            return 'table exists';
        }

        if ($generator === GeneratorName::RESOURCE && $context->presentationStack !== 'api') {
            return 'stack='.$context->presentationStack;
        }

        if ($generator === GeneratorName::VIEW && $context->presentationStack !== 'blade') {
            return 'stack='.$context->presentationStack;
        }

        return 'preset';
    }

    private function reasonForUnsupported(string $generator, GenerationContext $context): string
    {
        return match ($generator) {
            GeneratorName::RESOURCE, GeneratorName::VIEW => 'stack='.$context->presentationStack,
            GeneratorName::LANG => 'locale',
            default => 'preset',
        };
    }

    /**
     * @return list<ModuleGenerator>
     */
    public static function defaultGenerators(): array
    {
        return ModuleGeneratorCatalog::builtIn();
    }
}
