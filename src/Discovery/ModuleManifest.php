<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use Laravarc\Core\Module\ModulePathValidator;

final readonly class ModuleManifest
{
    /**
     * @param  list<ModuleManifestEntry>  $entries
     */
    public function __construct(
        public array $entries,
        public string $refreshedAt,
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
        return [
            'refreshedAt' => $this->refreshedAt,
            'modules' => array_map(
                static fn (ModuleManifestEntry $entry): array => $entry->toArray(),
                $this->entries,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $entries = array_map(
            static fn (array $entry): ModuleManifestEntry => ModuleManifestEntry::fromArray($entry),
            $data['modules'] ?? [],
        );

        return new self(
            entries: array_values($entries),
            refreshedAt: (string) ($data['refreshedAt'] ?? ''),
        );
    }
}
