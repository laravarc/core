<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class ListenTo
{
    /**
     * @param  class-string  $event
     */
    public function __construct(
        public readonly string $event,
    ) {}
}
