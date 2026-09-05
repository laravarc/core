<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Policy
{
    /**
     * @param  string|list<string>  $ability
     */
    public function __construct(
        public string|array $ability,
        public ?string $model = null,
    ) {}
}
