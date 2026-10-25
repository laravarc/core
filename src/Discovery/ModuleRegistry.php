<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use DateTimeImmutable;
use Laravarc\Core\Contracts\ModuleManifestStore;
use Laravarc\Core\Discovery\Exceptions\CorruptModuleManifestException;
use Laravarc\Core\Discovery\Exceptions\ModuleScanException;
use Laravarc\Core\Metadata\ListenerMetadataReader;
use Laravarc\Core\Metadata\ServiceMetadataReader;

final class ModuleRegistry
{
    public function __construct(
        private readonly ModuleScanner $scanner,
        private readonly ModuleManifestStore $store,
        private readonly string $modulesPath,
        private readonly string $moduleNamespace,
        private readonly ?ServiceMetadataReader $serviceReader = null,
        private readonly ?ListenerMetadataReader $listenerReader = null,
    ) {}

    public function refresh(): ModuleManifest
    {
        $timestamp = (new DateTimeImmutable)->format(DATE_ATOM);
        $entries = $this->scanner->scan($this->modulesPath, $this->moduleNamespace);
        $entries = array_map($this->enrich(...), $entries);

        $manifest = new ModuleManifest(
            entries: $entries,
            refreshedAt: $timestamp,
            fingerprint: $this->fingerprint($entries),
        );

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
                $path,
            ));
        }

        return $entry;
    }

    private function enrich(ModuleManifestEntry $entry): ModuleManifestEntry
    {
        $services = $this->serviceReader?->readModule($entry) ?? [];
        $listeners = $this->listenerReader?->readModule($entry) ?? [];

        return $entry->withBindings($services, $listeners);
    }

    /**
     * @param  list<ModuleManifestEntry>  $entries
     */
    private function fingerprint(array $entries): string
    {
        $payload = [
            'schema' => ModuleManifest::SCHEMA_VERSION,
            'modules_path' => $this->modulesPath,
            'modules' => array_map(
                static fn (ModuleManifestEntry $entry): array => $entry->toArray(),
                $entries,
            ),
        ];

        return 'sha256:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
