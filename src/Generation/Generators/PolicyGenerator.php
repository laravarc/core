<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

final class PolicyGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::POLICY;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('policy')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $policy = $context->classFor('policy');
        $model = $context->classFor('model');

        return [
            'namespace' => $context->moduleNamespace.'\\Policies',
            'class' => $policy['shortName'],
            'modelClass' => $model['className'],
            'modelShortName' => $model['shortName'],
            'entityVariable' => $context->entityVariable,
        ];
    }
}
