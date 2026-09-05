<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

trait SupportsSelectedGenerator
{
    protected function isSelected(GenerationContext $context): bool
    {
        return in_array($this->name(), $context->selectedGenerators, true);
    }
}
