<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class CommandContract
{
    public function __construct(
        public readonly ?string $description = null,
    ) {}
}
