<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures;

use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Metadata\MetadataArtifact;

final class FakeMetadataReader implements MetadataReader
{
    public function __construct(
        private readonly MetadataArtifact $artifact,
    ) {}

    public function artifact(): MetadataArtifact
    {
        return $this->artifact;
    }
}
