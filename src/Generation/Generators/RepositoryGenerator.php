<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

final class RepositoryGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::REPOSITORY;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('repository')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $repository = $context->classFor('repository');
        $model = $context->classFor('model');

        return [
            'namespace' => $context->moduleNamespace.'\\Repositories',
            'class' => $repository['shortName'],
            'modelClass' => $model['className'],
            'modelShortName' => $model['shortName'],
            'entityVariable' => $context->entityVariable,
            'collectionVariable' => $context->collectionVariable,
        ];
    }
}
