<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

final readonly class MetadataCompileResult
{
    /**
     * @param  list<string>  $moduleKeys
     */
    public function __construct(
        public MetadataArtifact $artifact,
        public int $moduleCount,
        public array $moduleKeys,
        public int $menuCount,
        public int $featureCount,
        public int $policyCount,
        public bool $persisted,
    ) {}
}
