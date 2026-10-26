<?php

declare(strict_types=1);

namespace Laravarc\Core\Facades;

use Laravarc\Core\Catalog\CatalogManager;
use Laravarc\Core\Catalog\CatalogQuery;

final class Catalog
{
    public static function forUser(mixed $user): CatalogQuery
    {
        return self::manager()->forUser($user);
    }

    public static function all(): CatalogQuery
    {
        return self::manager()->all();
    }

    public static function query(): CatalogQuery
    {
        return self::manager()->query();
    }

    private static function manager(): CatalogManager
    {
        return app(CatalogManager::class);
    }
}
