<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

use Illuminate\Database\Connection;
use Laravarc\Core\Contracts\SchemaIntrospector;

final class ConnectionSchemaIntrospector implements SchemaIntrospector
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function hasTable(string $table): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable($table);
    }

    public function getColumns(string $table): array
    {
        return $this->connection->getSchemaBuilder()->getColumns($table);
    }

    public function getIndexes(string $table): array
    {
        return $this->connection->getSchemaBuilder()->getIndexes($table);
    }

    public function getForeignKeys(string $table): array
    {
        return $this->connection->getSchemaBuilder()->getForeignKeys($table);
    }

    public function driverName(): string
    {
        return $this->connection->getDriverName();
    }

    public function connectionName(): string
    {
        return $this->connection->getName();
    }
}
