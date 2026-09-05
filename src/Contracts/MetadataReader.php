<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Metadata\MetadataArtifact;

interface MetadataReader
{
    public function artifact(): MetadataArtifact;
}
