<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Stores;

use Laravarc\Core\Contracts\MetadataArtifactStore;
use Laravarc\Core\Metadata\Exceptions\CorruptMetadataArtifactException;
use Laravarc\Core\Metadata\MetadataArtifact;

abstract class AbstractFileMetadataArtifactStore implements MetadataArtifactStore
{
    public function __construct(
        protected readonly string $path,
    ) {}

    public function isPersistent(): bool
    {
        return true;
    }

    public function read(): ?MetadataArtifact
    {
        if (! is_file($this->path)) {
            return null;
        }

        try {
            return $this->decode($this->path);
        } catch (\Throwable $exception) {
            throw new CorruptMetadataArtifactException(
                sprintf('Metadata artifact at [%s] is corrupt. Run laravarc:metadata compile.', $this->path),
                previous: $exception,
            );
        }
    }

    public function write(MetadataArtifact $artifact): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new CorruptMetadataArtifactException(sprintf(
                'Unable to create metadata directory [%s].',
                $directory,
            ));
        }

        $temporaryPath = $this->path.'.'.uniqid('arc-metadata-', true).'.tmp';
        $encoded = $this->encode($artifact);

        if (file_put_contents($temporaryPath, $encoded) === false) {
            throw new CorruptMetadataArtifactException(sprintf(
                'Unable to write temporary metadata file [%s].',
                $temporaryPath,
            ));
        }

        if (! rename($temporaryPath, $this->path)) {
            @unlink($temporaryPath);

            throw new CorruptMetadataArtifactException(sprintf(
                'Unable to publish metadata artifact to [%s].',
                $this->path,
            ));
        }
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    abstract protected function encode(MetadataArtifact $artifact): string;

    abstract protected function decode(string $path): MetadataArtifact;
}
