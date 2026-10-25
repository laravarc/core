<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use Laravarc\Core\Discovery\Exceptions\CorruptModuleManifestException;
use Laravarc\Core\Module\ModulePathValidator;

final readonly class ModuleManifest
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  list<ModuleManifestEntry>  $entries
     */
    public function __construct(
        public array $entries,
        public string $refreshedAt,
        public string $fingerprint = '',
    ) {}

    /**
     * @return list<ModuleManifestEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function findByPath(string $path): ?ModuleManifestEntry
    {
        $normalized = implode('/', (new ModulePathValidator)->normalize(trim($path, '/')));

        foreach ($this->entries as $entry) {
            if ($entry->path === $normalized) {
                return $entry;
            }
        }

        return null;
    }

    public function findByKey(string $key): ?ModuleManifestEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->key === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $sorted = $this->entries;
        usort($sorted, static fn (ModuleManifestEntry $left, ModuleManifestEntry $right): int => strcmp($left->path, $right->path));

        $modules = [];

        foreach ($sorted as $entry) {
            $modules[$entry->key] = $entry->toArray();
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'compiled_at' => $this->refreshedAt,
            'generated_by' => 'laravarc core',
            'fingerprint' => $this->fingerprint,
            'modules' => $modules,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $modulesRoot): self
    {
        if ((int) ($data['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            throw new CorruptModuleManifestException(sprintf(
                'Unsupported module manifest schema_version [%s]; expected [%d]. Run laravarc:cache build.',
                (string) ($data['schema_version'] ?? 'missing'),
                self::SCHEMA_VERSION,
            ));
        }

        $modules = $data['modules'] ?? [];

        if (! is_array($modules)) {
            throw new CorruptModuleManifestException('Module manifest "modules" must be an array.');
        }

        $entries = [];

        foreach ($modules as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entries[] = ModuleManifestEntry::fromArray($entry, $modulesRoot);
        }

        usort($entries, static fn (ModuleManifestEntry $left, ModuleManifestEntry $right): int => strcmp($left->path, $right->path));

        return new self(
            entries: $entries,
            refreshedAt: (string) ($data['compiled_at'] ?? ''),
            fingerprint: (string) ($data['fingerprint'] ?? ''),
        );
    }
}
