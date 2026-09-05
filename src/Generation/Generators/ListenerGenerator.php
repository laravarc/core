<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

final class ListenerGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::LISTENER;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('listener')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $listener = $context->classFor('listener');
        $event = $context->classFor('event');

        return [
            'namespace' => $context->moduleNamespace.'\\Listeners',
            'class' => $listener['shortName'],
            'eventClass' => $event['className'],
            'eventShortName' => $event['shortName'],
            'listenAttributeUse' => 'use Laravarc\\Core\\Metadata\\Attributes\\ListenTo;',
            'listenAttribute' => '#[ListenTo('.$event['shortName'].'::class)]',
        ];
    }
}
