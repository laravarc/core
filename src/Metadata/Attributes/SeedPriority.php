<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class SeedPriority
{
    public function __construct(
        public int $priority,
    ) {}
}
