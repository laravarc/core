<?php

declare(strict_types=1);

namespace Laravarc\Core\Support;

final class CachePath
{
    private static ?string $baseDirectoryOverride = null;

    public static function useBaseDirectory(?string $absolute): void
    {
        if ($absolute === null || $absolute === '') {
            self::$baseDirectoryOverride = null;

            return;
        }

        self::$baseDirectoryOverride = rtrim(str_replace('\\', '/', $absolute), '/');
    }

    public static function directory(): string
    {
        if (self::$baseDirectoryOverride !== null) {
            return self::$baseDirectoryOverride;
        }

        return rtrim(str_replace('\\', '/', base_path('bootstrap/cache/laravarc')), '/');
    }

    public static function manifest(): string
    {
        return self::directory().'/core-manifest.php';
    }

    public static function metadata(): string
    {
        return self::directory().'/core-metadata.php';
    }

    public static function abilities(): string
    {
        return self::directory().'/authorizer-abilities.php';
    }

    public static function catalogState(): string
    {
        return self::directory().'/catalog-state.php';
    }

    public static function schemaDirectory(): string
    {
        return rtrim(str_replace('\\', '/', storage_path('framework/cache/laravarc-schema')), '/');
    }
}
