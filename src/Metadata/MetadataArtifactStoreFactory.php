<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Laravarc\Core\Contracts\MetadataArtifactStore;
use Laravarc\Core\Metadata\Stores\CacheMetadataArtifactStore;
use Laravarc\Core\Metadata\Stores\FileMetadataArtifactStore;
use Laravarc\Core\Metadata\Stores\JsonMetadataArtifactStore;
use Laravarc\Core\Metadata\Stores\NullMetadataArtifactStore;

final class MetadataArtifactStoreFactory
{
    public function make(
        string $driver,
        string $filePath,
        string $jsonPath,
        CacheRepository $cache,
        string $cacheKey,
    ): MetadataArtifactStore {
        return match ($driver) {
            'file' => new FileMetadataArtifactStore($filePath),
            'json' => new JsonMetadataArtifactStore($jsonPath),
            'cache' => new CacheMetadataArtifactStore($cache, $cacheKey),
            'null' => new NullMetadataArtifactStore,
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported metadata store driver [%s]. Expected file, json, cache, or null.',
                $driver,
            )),
        };
    }
}
