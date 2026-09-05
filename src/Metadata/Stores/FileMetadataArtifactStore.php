<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Stores;

use Laravarc\Core\Metadata\MetadataArtifact;

final class FileMetadataArtifactStore extends AbstractFileMetadataArtifactStore
{
    protected function encode(MetadataArtifact $artifact): string
    {
        $exported = var_export($artifact->toArray(), true);

        return <<<PHP
<?php

declare(strict_types=1);

return {$exported};

PHP;
    }

    protected function decode(string $path): MetadataArtifact
    {
        /** @var mixed $data */
        $data = include $path;

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Metadata file must return an array.');
        }

        return MetadataArtifact::fromArray($data);
    }
}
