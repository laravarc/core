<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Generation;

final readonly class GenerationSummaryLine
{
    public function __construct(
        public string $status,
        public string $label,
        public ?string $reason = null,
    ) {}
}
