<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery\Stores;

use Laravarc\Core\Contracts\ModuleManifestStore;
use Laravarc\Core\Discovery\ModuleManifest;

final class NullModuleManifestStore implements ModuleManifestStore
{
    public function read(): ?ModuleManifest
    {
        return null;
    }

    public function write(ModuleManifest $manifest): void
    {
        // Intentionally no-op: null store never persists manifest artifacts.
    }

    public function clear(): void
    {
        // Intentionally no-op.
    }

    public function isPersistent(): bool
    {
        return false;
    }
}
