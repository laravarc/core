<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Stores;

use Laravarc\Core\Metadata\MetadataArtifact;

final class JsonMetadataArtifactStore extends AbstractFileMetadataArtifactStore
{
    protected function encode(MetadataArtifact $artifact): string
    {
        return json_encode($artifact->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    protected function decode(string $path): MetadataArtifact
    {
        $contents = (string) file_get_contents($path);
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Metadata JSON must decode to an array.');
        }

        return MetadataArtifact::fromArray($data);
    }
}
