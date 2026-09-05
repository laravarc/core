<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Contracts\ModuleGenerator;
use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\StubRenderer;

abstract class AbstractStubGenerator implements ModuleGenerator
{
    use SupportsSelectedGenerator;

    abstract protected function variables(GenerationContext $context): array;

    abstract protected function relativePath(GenerationContext $context): string;

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context);
    }

    public function generate(GenerationContext $context): array
    {
        $template = (string) file_get_contents($context->stubPath($this->name()));

        return [
            new GeneratedFile(
                relativePath: $this->relativePath($context),
                contents: StubRenderer::render($template, $this->variables($context)),
            ),
        ];
    }
}
