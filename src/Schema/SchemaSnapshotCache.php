<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

final class SchemaSnapshotCache
{
    public function __construct(
        private readonly string $directory,
    ) {}

    public function get(string $connection, string $table): ?SchemaSnapshot
    {
        $path = $this->path($connection, $table);

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return SchemaSnapshot::fromArray($data);
    }

    public function put(SchemaSnapshot $snapshot): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0777, true) && ! is_dir($this->directory)) {
            return;
        }

        $connection = $snapshot->connection ?? 'default';
        $path = $this->path($connection, $snapshot->tableName);

        file_put_contents(
            $path,
            json_encode($snapshot->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    public function clear(): void
    {
        if (! is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory.'/*.json') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function path(string $connection, string $table): string
    {
        $safeConnection = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $connection) ?: 'default';
        $safeTable = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $table) ?: 'table';

        return rtrim($this->directory, '/')."/{$safeConnection}__{$safeTable}.json";
    }
}
