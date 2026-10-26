<?php

declare(strict_types=1);

namespace Laravarc\Core\Facades;

use Illuminate\Support\Facades\Facade;
use Laravarc\Core\Catalog\CatalogManager;
use Laravarc\Core\Catalog\CatalogQuery;

/**
 * @method static CatalogQuery forUser(mixed $user)
 * @method static CatalogQuery all()
 * @method static CatalogQuery query()
 * @method static CatalogQuery forArea(string $area)
 *
 * @see CatalogManager
 */
final class Catalog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CatalogManager::class;
    }
}
