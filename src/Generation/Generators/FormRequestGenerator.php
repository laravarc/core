<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Contracts\ModuleGenerator;
use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Generation\StubRenderer;

final class FormRequestGenerator implements ModuleGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::FORM_REQUEST;
    }

    public function supports(GenerationContext $context): bool
    {
        return in_array($this->name(), $context->selectedGenerators, true)
            && $context->schemaSnapshot !== null;
    }

    public function generate(GenerationContext $context): array
    {
        $files = [];

        foreach ($context->formRequestActions as $action) {
            $request = $context->classFor('form_request_'.$action);
            $template = (string) file_get_contents($context->stubPath($this->name()));

            $files[] = new GeneratedFile(
                relativePath: $request['relativePath'],
                contents: StubRenderer::render($template, [
                    'namespace' => $context->moduleNamespace.'\\FormRequests',
                    'class' => $request['shortName'],
                    'rules' => $this->buildRules($context, $action),
                ]),
            );
        }

        return $files;
    }

    private function buildRules(GenerationContext $context, string $action): string
    {
        if ($action === 'destroy') {
            return '';
        }

        $rules = [];

        foreach ($context->schemaSnapshot->columns as $column) {
            if ($column->autoIncrement || in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $rule = $column->nullable || $action === 'update' ? "'".$column->name."' => ['nullable']" : "'".$column->name."' => ['required']";
            $rules[] = '            '.$rule.',';
        }

        return implode(PHP_EOL, $rules);
    }
}
