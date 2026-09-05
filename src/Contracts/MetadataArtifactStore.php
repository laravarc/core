<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Metadata\MetadataArtifact;

interface MetadataArtifactStore
{
    public function read(): ?MetadataArtifact;

    public function write(MetadataArtifact $artifact): void;

    public function clear(): void;

    public function isPersistent(): bool;
}
