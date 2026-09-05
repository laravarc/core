<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

final readonly class GenerationRunResult
{
    /**
     * @param  list<string>  $writtenFiles
     * @param  list<string>  $generatedGenerators
     * @param  list<GenerationFailure>  $failures
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $writtenFiles,
        public array $generatedGenerators,
        public array $failures,
        public array $warnings = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->failures === [];
    }
}
