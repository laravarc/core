<?php

declare(strict_types=1);

namespace Laravarc\Core\Support;

use Illuminate\Support\Str;

/**
 * Canonical dotted snake_case identifiers for FQCNs and filesystem paths.
 *
 * Class basenames keep their suffix (Policy, Controller, …). Menu.key / Feature.key
 * and other declared attribute keys are out of scope.
 */
final class KeyIdentifier
{
    public static function fromClass(string $className): string
    {
        $segments = explode('\\', ltrim($className, '\\'));

        return implode('.', array_map(
            static fn (string $segment): string => Str::snake($segment),
            $segments,
        ));
    }

    public static function fromPath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\.[^.\/]+$/', '', $normalized) ?? $normalized;
        $segments = array_values(array_filter(
            explode('/', $normalized),
            static fn (string $segment): bool => $segment !== '',
        ));

        return implode('.', array_map(
            static fn (string $segment): string => Str::snake($segment),
            $segments,
        ));
    }
}
