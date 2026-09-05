<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

final class ModelCastMapper
{
    public function castForColumn(ColumnSnapshot $column): ?string
    {
        return match ($column->laravelType) {
            'integer' => 'integer',
            'boolean' => 'boolean',
            'float' => 'float',
            'decimal' => $this->decimalCast($column),
            'array' => 'array',
            'date' => 'date',
            'datetime' => 'datetime',
            'string' => null,
            default => null,
        };
    }

    private function decimalCast(ColumnSnapshot $column): string
    {
        $precision = $column->precision ?? 8;
        $scale = $column->scale ?? 2;

        return sprintf('decimal:%d:%d', $precision, $scale);
    }
}
