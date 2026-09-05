<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

final class SeederGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::SEEDER;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('seeder')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $seeder = $context->classFor('seeder');
        $model = $context->classFor('model');

        return [
            'namespace' => $context->moduleNamespace.'\\Database\\Seeders',
            'class' => $seeder['shortName'],
            'modelClass' => $model['className'],
            'modelShortName' => $model['shortName'],
        ];
    }
}
