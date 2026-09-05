<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery\Stores;

use Laravarc\Core\Contracts\ModuleManifestStore;
use Laravarc\Core\Discovery\Exceptions\CorruptModuleManifestException;
use Laravarc\Core\Discovery\ModuleManifest;

abstract class AbstractFileModuleManifestStore implements ModuleManifestStore
{
    public function __construct(
        protected readonly string $path,
    ) {}

    public function isPersistent(): bool
    {
        return true;
    }

    public function read(): ?ModuleManifest
    {
        if (! is_file($this->path)) {
            return null;
        }

        try {
            return $this->decode((string) file_get_contents($this->path));
        } catch (\Throwable $exception) {
            throw new CorruptModuleManifestException(
                sprintf('Module manifest at [%s] is corrupt. Run laravarc:cache refresh.', $this->path),
                previous: $exception,
            );
        }
    }

    public function write(ModuleManifest $manifest): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new CorruptModuleManifestException(sprintf(
                'Unable to create manifest directory [%s].',
                $directory,
            ));
        }

        $temporaryPath = $this->path.'.'.uniqid('arc-manifest-', true).'.tmp';
        $encoded = $this->encode($manifest);

        if (file_put_contents($temporaryPath, $encoded) === false) {
            throw new CorruptModuleManifestException(sprintf(
                'Unable to write temporary manifest file [%s].',
                $temporaryPath,
            ));
        }

        if (! rename($temporaryPath, $this->path)) {
            @unlink($temporaryPath);

            throw new CorruptModuleManifestException(sprintf(
                'Unable to publish module manifest to [%s].',
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

    abstract protected function encode(ModuleManifest $manifest): string;

    abstract protected function decode(string $contents): ModuleManifest;
}
