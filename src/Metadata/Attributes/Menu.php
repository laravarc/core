<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Menu
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $icon = null,
        public ?string $group = null,
        public ?int $order = null,
        public ?string $parent = null,
        public ?bool $visible = null,
    ) {}
}
