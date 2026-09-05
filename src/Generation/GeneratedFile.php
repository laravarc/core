<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

final readonly class GeneratedFile
{
    public function __construct(
        public string $relativePath,
        public string $contents,
        public ?string $absolutePath = null,
    ) {}
}
