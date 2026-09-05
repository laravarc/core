<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Discovery\ModuleManifest;

interface ModuleManifestStore
{
    public function read(): ?ModuleManifest;

    public function write(ModuleManifest $manifest): void;

    public function clear(): void;

    public function isPersistent(): bool;
}
