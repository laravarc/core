<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

final class ContractInterfaceReader
{
    public function interfaceName(string $path): ?string
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            return null;
        }

        if (preg_match('/\binterface\s+([A-Za-z_][A-Za-z0-9_]*)/', $content, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    public function methodNames(string $path): array
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            return [];
        }

        if (preg_match_all('/public\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $matches) === 0) {
            return [];
        }

        return array_values(array_unique($matches[1] ?? []));
    }
}
