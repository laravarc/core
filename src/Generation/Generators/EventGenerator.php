<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Illuminate\Support\Str;
use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Generation\StubRenderer;

final class EventGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::EVENT;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    public function generate(GenerationContext $context): array
    {
        if (! $context->withEvents) {
            return parent::generate($context);
        }

        return [
            $this->renderEventFile($context, $context->classFor('event')),
            $this->renderEventFile($context, $context->classFor('event_deleted')),
        ];
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('event')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        return $this->eventVariables($context, $context->classFor('event'));
    }

    /**
     * @param  array{className: string, relativePath: string, shortName: string, absolutePath?: string|null}  $event
     */
    private function renderEventFile(GenerationContext $context, array $event): GeneratedFile
    {
        return new GeneratedFile(
            relativePath: $event['relativePath'],
            contents: StubRenderer::render(
                (string) file_get_contents($context->stubPath($this->name())),
                $this->eventVariables($context, $event),
            ),
            absolutePath: $event['absolutePath'] ?? null,
        );
    }

    /**
     * @param  array{className: string, relativePath: string, shortName: string, absolutePath?: string|null}  $event
     * @return array<string, string>
     */
    private function eventVariables(GenerationContext $context, array $event): array
    {
        $entityIdProperty = Str::camel($context->moduleName).'Id';
        $namespace = substr($event['className'], 0, (int) strrpos($event['className'], '\\'));

        return [
            'namespace' => $namespace,
            'class' => $event['shortName'],
            'entityIdProperty' => $entityIdProperty,
        ];
    }
}
