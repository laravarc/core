<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use DateTimeImmutable;
use Laravarc\Core\Contracts\ModuleManifestStore;
use Laravarc\Core\Discovery\Exceptions\CorruptModuleManifestException;
use Laravarc\Core\Discovery\Exceptions\ModuleScanException;

final class ModuleRegistry
{
    public function __construct(
        private readonly ModuleScanner $scanner,
        private readonly ModuleManifestStore $store,
        private readonly string $modulesPath,
        private readonly string $moduleNamespace,
    ) {}

    public function refresh(): ModuleManifest
    {
        $timestamp = (new DateTimeImmutable)->format(DATE_ATOM);
        $entries = $this->scanner->scan($this->modulesPath, $this->moduleNamespace, $timestamp);
        $manifest = new ModuleManifest($entries, $timestamp);

        if ($this->store->isPersistent()) {
            $this->store->write($manifest);
        }

        return $manifest;
    }

    public function clear(): void
    {
        $this->store->clear();
    }

    public function manifest(): ModuleManifest
    {
        if ($this->store->isPersistent()) {
            $manifest = $this->store->read();

            if ($manifest !== null) {
                return $manifest;
            }
        }

        return $this->refresh();
    }

    public function findByPath(string $path): ?ModuleManifestEntry
    {
        return $this->manifest()->findByPath($path);
    }

    public function findByKey(string $key): ?ModuleManifestEntry
    {
        return $this->manifest()->findByKey($key);
    }

    /**
     * @return list<ModuleManifestEntry>
     */
    public function all(): array
    {
        return $this->manifest()->all();
    }

    /**
     * @throws ModuleScanException
     * @throws CorruptModuleManifestException
     */
    public function requireByPath(string $path): ModuleManifestEntry
    {
        $entry = $this->findByPath($path);

        if ($entry === null) {
            throw new ModuleScanException(sprintf(
                'Module not found at path [%s].',
                trim($path, '/'),
            ));
        }

        return $entry;
    }
}
