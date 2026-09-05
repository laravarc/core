<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Stores;

use Laravarc\Core\Contracts\MetadataArtifactStore;
use Laravarc\Core\Metadata\MetadataArtifact;

final class NullMetadataArtifactStore implements MetadataArtifactStore
{
    public function read(): ?MetadataArtifact
    {
        return null;
    }

    public function write(MetadataArtifact $artifact): void
    {
        // No-op: null driver does not persist artifacts.
    }

    public function clear(): void
    {
        // No-op.
    }

    public function isPersistent(): bool
    {
        return false;
    }
}
