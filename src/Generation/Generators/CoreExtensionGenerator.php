<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Illuminate\Support\Str;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

final class CoreExtensionGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::CORE_EXTENSION;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->withExtension;
    }

    protected function relativePath(GenerationContext $context): string
    {
        return 'Extensions/'.$this->basename($context).'CoreExtension.php';
    }

    protected function variables(GenerationContext $context): array
    {
        $basename = $this->basename($context);

        return [
            'moduleNamespace' => $context->moduleNamespace,
            'modulePath' => $context->modulePath,
            'extensionClass' => $basename.'CoreExtension',
            'extensionKey' => str_replace('/', '.', Str::lower($context->modulePath)),
        ];
    }

    protected function stubName(GenerationContext $context): string
    {
        return 'core-extension';
    }

    public function generate(GenerationContext $context): array
    {
        $template = (string) file_get_contents($context->namedStubPath($this->stubName($context)));

        return [
            new \Laravarc\Core\Generation\GeneratedFile(
                relativePath: $this->relativePath($context),
                contents: \Laravarc\Core\Generation\StubRenderer::render($template, $this->variables($context)),
            ),
        ];
    }

    private function basename(GenerationContext $context): string
    {
        $segments = array_values(array_filter(explode('/', $context->modulePath)));

        return Str::studly((string) ($segments[array_key_last($segments)] ?? $context->moduleName));
    }
}
