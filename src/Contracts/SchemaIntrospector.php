<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

interface SchemaIntrospector
{
    public function hasTable(string $table): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function getColumns(string $table): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getIndexes(string $table): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getForeignKeys(string $table): array;

    public function driverName(): string;

    public function connectionName(): string;
}
