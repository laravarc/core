<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

final readonly class SchemaSnapshot
{
    /**
     * @param  list<string>  $primaryKey
     * @param  list<ColumnSnapshot>  $columns
     */
    public function __construct(
        public string $tableName,
        public array $primaryKey,
        public array $columns,
        public bool $timestamps,
        public bool $softDeletes,
        public string $driver,
        public ?string $connection = null,
    ) {}

    public function hasCompositePrimaryKey(): bool
    {
        return count($this->primaryKey) > 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tableName' => $this->tableName,
            'primaryKey' => $this->primaryKey,
            'columns' => array_map(
                static fn (ColumnSnapshot $column): array => $column->toArray(),
                $this->columns,
            ),
            'timestamps' => $this->timestamps,
            'softDeletes' => $this->softDeletes,
            'driver' => $this->driver,
            'connection' => $this->connection,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $columns = array_map(
            static fn (array $column): ColumnSnapshot => ColumnSnapshot::fromArray($column),
            $data['columns'] ?? [],
        );

        return new self(
            tableName: (string) $data['tableName'],
            primaryKey: array_values($data['primaryKey'] ?? []),
            columns: array_values($columns),
            timestamps: (bool) ($data['timestamps'] ?? false),
            softDeletes: (bool) ($data['softDeletes'] ?? false),
            driver: (string) $data['driver'],
            connection: $data['connection'] ?? null,
        );
    }
}
