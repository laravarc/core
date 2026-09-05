<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

use Laravarc\Core\Contracts\SchemaIntrospector;
use Laravarc\Core\Contracts\SchemaIntrospectorFactoryContract;
use Laravarc\Core\Contracts\SchemaReader;
use Laravarc\Core\Schema\Exceptions\MissingPrimaryKeyException;
use Laravarc\Core\Schema\Exceptions\SchemaReadException;
use Throwable;

final class DatabaseSchemaReader implements SchemaReader
{
    public function __construct(
        private readonly SchemaIntrospectorFactoryContract $introspectorFactory,
        private readonly ColumnTypeMapper $columnTypeMapper,
    ) {}

    public function tableExists(string $table, ?string $connection = null): bool
    {
        $this->assertValidIdentifier($table);

        return $this->introspectorFactory->forConnection($connection)->hasTable($table);
    }

    public function read(string $table, ?string $connection = null): SchemaSnapshot
    {
        $this->assertValidIdentifier($table);

        try {
            $introspector = $this->introspectorFactory->forConnection($connection);

            if (! $introspector->hasTable($table)) {
                throw new SchemaReadException(sprintf(
                    'Table [%s] was not found on connection [%s].',
                    $table,
                    $introspector->connectionName(),
                ));
            }

            $rawColumns = $introspector->getColumns($table);

            if ($rawColumns === []) {
                throw new SchemaReadException(sprintf(
                    'Table [%s] has no readable columns.',
                    $table,
                ));
            }

            $indexes = $introspector->getIndexes($table);
            $foreignKeys = $introspector->getForeignKeys($table);
            $primaryKey = $this->resolvePrimaryKey($indexes, $table);
            $columnNames = array_column($rawColumns, 'name');

            $columns = array_map(
                fn (array $column): ColumnSnapshot => $this->mapColumn(
                    column: $column,
                    primaryKey: $primaryKey,
                    indexes: $indexes,
                    foreignKeys: $foreignKeys,
                ),
                $rawColumns,
            );

            return new SchemaSnapshot(
                tableName: $table,
                primaryKey: $primaryKey,
                columns: array_values($columns),
                timestamps: in_array('created_at', $columnNames, true)
                    && in_array('updated_at', $columnNames, true),
                softDeletes: in_array('deleted_at', $columnNames, true),
                driver: $introspector->driverName(),
                connection: $introspector->connectionName(),
            );
        } catch (MissingPrimaryKeyException|SchemaReadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SchemaReadException(sprintf(
                'Unable to read schema for table [%s].',
                $table,
            ), previous: $exception);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $indexes
     * @return list<string>
     */
    private function resolvePrimaryKey(array $indexes, string $table): array
    {
        foreach ($indexes as $index) {
            if (($index['primary'] ?? false) === true) {
                return array_values($index['columns']);
            }
        }

        throw new MissingPrimaryKeyException(sprintf(
            'Table [%s] does not define a primary key.',
            $table,
        ));
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  list<string>  $primaryKey
     * @param  list<array<string, mixed>>  $indexes
     * @param  list<array<string, mixed>>  $foreignKeys
     */
    private function mapColumn(array $column, array $primaryKey, array $indexes, array $foreignKeys): ColumnSnapshot
    {
        $name = (string) $column['name'];
        $databaseType = (string) $column['type'];
        $databaseTypeName = (string) $column['type_name'];
        $typeDetails = $this->parseTypeDetails($databaseType);

        return new ColumnSnapshot(
            name: $name,
            databaseType: $databaseTypeName,
            laravelType: $this->columnTypeMapper->map($databaseTypeName, $databaseType),
            nullable: (bool) $column['nullable'],
            default: $column['default'] ?? null,
            autoIncrement: (bool) ($column['auto_increment'] ?? false),
            unsigned: str_contains(strtolower($databaseType), 'unsigned'),
            length: $typeDetails['length'],
            precision: $typeDetails['precision'],
            scale: $typeDetails['scale'],
            isPrimaryKey: in_array($name, $primaryKey, true),
            isUnique: $this->columnIsUnique($name, $indexes, $primaryKey),
            isIndexed: $this->columnIsIndexed($name, $indexes),
            foreignKey: $this->resolveForeignKey($name, $foreignKeys),
        );
    }

    /**
     * @return array{length: ?int, precision: ?int, scale: ?int}
     */
    private function parseTypeDetails(string $databaseType): array
    {
        if (preg_match('/\((\d+)(?:,\s*(\d+))?\)/', $databaseType, $matches) !== 1) {
            return [
                'length' => null,
                'precision' => null,
                'scale' => null,
            ];
        }

        if (isset($matches[2])) {
            return [
                'length' => null,
                'precision' => (int) $matches[1],
                'scale' => (int) $matches[2],
            ];
        }

        return [
            'length' => (int) $matches[1],
            'precision' => null,
            'scale' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $indexes
     * @param  list<string>  $primaryKey
     */
    private function columnIsUnique(string $column, array $indexes, array $primaryKey): bool
    {
        if (in_array($column, $primaryKey, true)) {
            return true;
        }

        foreach ($indexes as $index) {
            if (($index['unique'] ?? false) === true && ($index['columns'] ?? []) === [$column]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $indexes
     */
    private function columnIsIndexed(string $column, array $indexes): bool
    {
        foreach ($indexes as $index) {
            if (in_array($column, $index['columns'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $foreignKeys
     */
    private function resolveForeignKey(string $column, array $foreignKeys): ?ForeignKeySnapshot
    {
        foreach ($foreignKeys as $foreignKey) {
            $columns = $foreignKey['columns'] ?? [];
            $columnIndex = array_search($column, $columns, true);

            if ($columnIndex === false) {
                continue;
            }

            $referencedColumns = $foreignKey['foreign_columns'] ?? [];

            return new ForeignKeySnapshot(
                referencedTable: (string) $foreignKey['foreign_table'],
                referencedColumn: (string) ($referencedColumns[$columnIndex] ?? $referencedColumns[0] ?? ''),
                onDelete: $foreignKey['on_delete'] ?? null,
                onUpdate: $foreignKey['on_update'] ?? null,
            );
        }

        return null;
    }

    private function assertValidIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) !== 1) {
            throw new SchemaReadException(sprintf(
                'Table name [%s] is not a valid database identifier.',
                $identifier,
            ));
        }
    }
}
