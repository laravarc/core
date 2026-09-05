<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures;

use Laravarc\Core\Contracts\SchemaIntrospector;

final class FakeSchemaIntrospector implements SchemaIntrospector
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $columns
     * @param  array<string, list<array<string, mixed>>>  $indexes
     * @param  array<string, list<array<string, mixed>>>  $foreignKeys
     */
    public function __construct(
        private array $columns = [],
        private array $indexes = [],
        private array $foreignKeys = [],
        private string $driver = 'sqlite',
        private string $connection = 'testing',
    ) {}

    public function hasTable(string $table): bool
    {
        return array_key_exists($table, $this->columns);
    }

    public function getColumns(string $table): array
    {
        return $this->columns[$table] ?? [];
    }

    public function getIndexes(string $table): array
    {
        return $this->indexes[$table] ?? [];
    }

    public function getForeignKeys(string $table): array
    {
        return $this->foreignKeys[$table] ?? [];
    }

    public function driverName(): string
    {
        return $this->driver;
    }

    public function connectionName(): string
    {
        return $this->connection;
    }
}
