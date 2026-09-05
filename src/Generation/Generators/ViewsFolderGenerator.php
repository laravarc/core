<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Contracts\ModuleGenerator;
use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Module\ModuleLayout;

/**
 * Ensures the module Views/ folder exists for --stack=blade.
 * Does not scaffold .blade.php files — agents/devs hand-write templates there.
 */
final class ViewsFolderGenerator implements ModuleGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::VIEW;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context)
            && $context->presentationStack === 'blade'
            && $context->schemaSnapshot !== null;
    }

    public function generate(GenerationContext $context): array
    {
        return [
            new GeneratedFile(
                relativePath: ModuleLayout::VIEWS.'/.gitkeep',
                contents: '',
            ),
        ];
    }
}
