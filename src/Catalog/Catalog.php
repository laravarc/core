<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

/**
 * @deprecated 2.2 Use Laravarc\Core\Facades\Catalog
 */
final class Catalog
{
    public static function forUser(mixed $user): CatalogQuery
    {
        return \Laravarc\Core\Facades\Catalog::forUser($user);
    }

    public static function all(): CatalogQuery
    {
        return \Laravarc\Core\Facades\Catalog::all();
    }

    public static function forArea(string $area): CatalogQuery
    {
        return \Laravarc\Core\Facades\Catalog::forArea($area);
    }

    public static function query(): CatalogQuery
    {
        return \Laravarc\Core\Facades\Catalog::query();
    }
}
