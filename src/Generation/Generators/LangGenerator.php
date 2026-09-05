<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

final class LangGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::LANG;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context)
            && $context->schemaSnapshot !== null
            && $context->selectedLocale !== null
            && $context->selectedLocale !== '';
    }

    protected function relativePath(GenerationContext $context): string
    {
        return 'Lang/'.$context->selectedLocale.'/'.$context->moduleKey.'.php';
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        return [
            'moduleKey' => $context->moduleKey,
            'moduleName' => $context->moduleName,
        ];
    }
}
