<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery\Stores;

use Laravarc\Core\Discovery\ModuleManifest;

final class JsonModuleManifestStore extends AbstractFileModuleManifestStore
{
    protected function encode(ModuleManifest $manifest): string
    {
        return json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    protected function decode(string $contents): ModuleManifest
    {
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Manifest JSON must decode to an array.');
        }

        return ModuleManifest::fromArray($data);
    }
}
