<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use InvalidArgumentException;
use Laravarc\Core\Contracts\ModuleManifestStore;
use Laravarc\Core\Discovery\Stores\FileModuleManifestStore;
use Laravarc\Core\Discovery\Stores\JsonModuleManifestStore;
use Laravarc\Core\Discovery\Stores\NullModuleManifestStore;

final class ModuleManifestStoreFactory
{
    public function make(string $driver, string $filePath, string $jsonPath): ModuleManifestStore
    {
        return match ($driver) {
            'file' => new FileModuleManifestStore($filePath),
            'json' => new JsonModuleManifestStore($jsonPath),
            'null' => new NullModuleManifestStore,
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported manifest store driver [%s]. Supported drivers: file, json, null.',
                $driver,
            )),
        };
    }
}
