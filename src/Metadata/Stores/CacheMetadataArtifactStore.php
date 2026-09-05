<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Stores;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Laravarc\Core\Contracts\MetadataArtifactStore;
use Laravarc\Core\Metadata\Exceptions\CorruptMetadataArtifactException;
use Laravarc\Core\Metadata\MetadataArtifact;

final class CacheMetadataArtifactStore implements MetadataArtifactStore
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $cacheKey,
    ) {}

    public function isPersistent(): bool
    {
        return true;
    }

    public function read(): ?MetadataArtifact
    {
        $data = $this->cache->get($this->cacheKey);

        if ($data === null) {
            return null;
        }

        if (! is_array($data)) {
            throw new CorruptMetadataArtifactException(sprintf(
                'Metadata cache entry [%s] is corrupt. Run laravarc:metadata compile.',
                $this->cacheKey,
            ));
        }

        return MetadataArtifact::fromArray($data);
    }

    public function write(MetadataArtifact $artifact): void
    {
        $this->cache->forever($this->cacheKey, $artifact->toArray());
    }

    public function clear(): void
    {
        $this->cache->forget($this->cacheKey);
    }
}
