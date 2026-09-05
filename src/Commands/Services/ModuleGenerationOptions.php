<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Laravarc\Core\Commands\Support\MetadataOptionParser;
use Laravarc\Core\Generation\Metadata\MetadataSelection;

final readonly class ModuleGenerationOptions
{
    /**
     * @param  list<string>  $only
     * @param  list<string>  $except
     */
    public function __construct(
        public string $preset,
        public string $stack,
        public ?string $tableOverride,
        public array $only,
        public array $except,
        public bool $refresh,
        public bool $force,
        public bool $dryRun,
        public ?string $locale = null,
        public mixed $metadata = null,
        public bool $withContractAttributes = false,
        public bool $withExtension = false,
    ) {}

    public function metadataSelection(): MetadataSelection
    {
        return (new MetadataOptionParser)->parse($this->metadata);
    }

    public function emitsMetadata(): bool
    {
        return ! $this->metadataSelection()->isEmpty();
    }
}
