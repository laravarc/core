<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

final readonly class ColumnSnapshot
{
    public function __construct(
        public string $name,
        public string $databaseType,
        public string $laravelType,
        public bool $nullable,
        public mixed $default,
        public bool $autoIncrement,
        public bool $unsigned,
        public ?int $length,
        public ?int $precision,
        public ?int $scale,
        public bool $isPrimaryKey,
        public bool $isUnique,
        public bool $isIndexed,
        public ?ForeignKeySnapshot $foreignKey,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'databaseType' => $this->databaseType,
            'laravelType' => $this->laravelType,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'autoIncrement' => $this->autoIncrement,
            'unsigned' => $this->unsigned,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'isPrimaryKey' => $this->isPrimaryKey,
            'isUnique' => $this->isUnique,
            'isIndexed' => $this->isIndexed,
            'foreignKey' => $this->foreignKey?->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            databaseType: (string) $data['databaseType'],
            laravelType: (string) $data['laravelType'],
            nullable: (bool) $data['nullable'],
            default: $data['default'] ?? null,
            autoIncrement: (bool) $data['autoIncrement'],
            unsigned: (bool) $data['unsigned'],
            length: isset($data['length']) ? (int) $data['length'] : null,
            precision: isset($data['precision']) ? (int) $data['precision'] : null,
            scale: isset($data['scale']) ? (int) $data['scale'] : null,
            isPrimaryKey: (bool) $data['isPrimaryKey'],
            isUnique: (bool) $data['isUnique'],
            isIndexed: (bool) $data['isIndexed'],
            foreignKey: is_array($data['foreignKey'] ?? null)
                ? ForeignKeySnapshot::fromArray($data['foreignKey'])
                : null,
        );
    }
}
