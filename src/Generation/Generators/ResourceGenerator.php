<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

final class ResourceGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::RESOURCE;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context)
            && $context->schemaSnapshot !== null
            && $context->presentationStack === 'api';
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('resource')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $resource = $context->classFor('resource');
        $model = $context->classFor('model');

        return [
            'namespace' => $context->moduleNamespace.'\\Resources',
            'class' => $resource['shortName'],
            'modelClass' => $model['className'],
            'modelShortName' => $model['shortName'],
            'outcomeClass' => $context->moduleNamespace.'\\Support\\Outcomes\\'.$context->moduleName.'Outcome',
            'outcomeShortName' => $context->moduleName.'Outcome',
            'resultClass' => $context->moduleNamespace.'\\Support\\Results\\'.$context->moduleName.'Result',
            'resultShortName' => $context->moduleName.'Result',
        ];
    }
}
