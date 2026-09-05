<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

final readonly class GenerationFailure
{
    public function __construct(
        public string $generator,
        public string $message,
    ) {}
}
