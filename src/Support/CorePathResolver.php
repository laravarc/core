<?php

declare(strict_types=1);

namespace Laravarc\Core\Support;

use Illuminate\Support\Str;

final class CorePathResolver
{
    public static function resolve(string $path): string
    {
        if ($path === '') {
            return app_path();
        }

        if (self::isAbsolute($path)) {
            return rtrim(str_replace('\\', '/', $path), '/');
        }

        return rtrim(str_replace('\\', '/', app_path($path)), '/');
    }

    public static function namespaceFromPath(string $absolutePath): string
    {
        $appPath = rtrim(str_replace('\\', '/', app_path()), '/');
        $target = rtrim(str_replace('\\', '/', self::resolve($absolutePath)), '/');

        $relative = str_starts_with($target, $appPath.'/')
            ? substr($target, strlen($appPath) + 1)
            : basename($target);

        $segments = array_filter(explode('/', trim($relative, '/')));

        return implode('\\', array_map(
            static fn (string $segment): string => Str::studly($segment),
            $segments,
        ));
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}
