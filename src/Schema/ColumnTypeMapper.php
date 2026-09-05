<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

final class ColumnTypeMapper
{
    public function map(string $databaseTypeName, string $databaseType): string
    {
        $normalized = strtolower($databaseTypeName);

        return match ($normalized) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => 'integer',
            'boolean', 'bool' => 'boolean',
            'float', 'double', 'real' => 'float',
            'decimal', 'numeric' => 'decimal',
            'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'uuid' => 'string',
            'json', 'jsonb' => 'array',
            'date' => 'date',
            'datetime', 'timestamp', 'timestamptz' => 'datetime',
            'time' => 'string',
            'binary', 'blob', 'longblob', 'bytea' => 'string',
            default => str_contains(strtolower($databaseType), 'int') ? 'integer' : 'string',
        };
    }
}
