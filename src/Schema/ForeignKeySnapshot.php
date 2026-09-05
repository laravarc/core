<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

final readonly class ForeignKeySnapshot
{
    public function __construct(
        public string $referencedTable,
        public string $referencedColumn,
        public ?string $onDelete,
        public ?string $onUpdate,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'referencedTable' => $this->referencedTable,
            'referencedColumn' => $this->referencedColumn,
            'onDelete' => $this->onDelete,
            'onUpdate' => $this->onUpdate,
        ];
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            referencedTable: (string) $data['referencedTable'],
            referencedColumn: (string) $data['referencedColumn'],
            onDelete: $data['onDelete'] ?? null,
            onUpdate: $data['onUpdate'] ?? null,
        );
    }
}
