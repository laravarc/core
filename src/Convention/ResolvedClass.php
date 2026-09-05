<?php

declare(strict_types=1);

namespace Laravarc\Core\Convention;

final readonly class ResolvedClass
{
    public function __construct(
        public string $className,
        public string $relativePath,
    ) {}
}
