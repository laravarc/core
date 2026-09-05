<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Support;

final class CommaSeparatedOptionParser
{
    /**
     * @return list<string>
     */
    public static function parse(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }
}
