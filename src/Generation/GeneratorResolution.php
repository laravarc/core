<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

final readonly class GeneratorResolution
{
    /**
     * @param  list<string>  $generators
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $generators,
        public array $warnings = [],
    ) {}
}
