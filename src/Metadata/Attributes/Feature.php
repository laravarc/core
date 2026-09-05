<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Feature
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $menu = null,
        public ?string $placement = null,
        public ?int $order = null,
        public ?string $description = null,
        public ?string $visibilityAbility = null,
    ) {}
}
