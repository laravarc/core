<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;

interface ModuleGenerator
{
    public function name(): string;

    public function supports(GenerationContext $context): bool;

    /**
     * @return list<GeneratedFile>
     */
    public function generate(GenerationContext $context): array;
}
