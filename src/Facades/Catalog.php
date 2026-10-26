<?php

declare(strict_types=1);

namespace Laravarc\Core\Facades;

use Illuminate\Support\Facades\Facade;
use Laravarc\Core\Catalog\CatalogManager;
use Laravarc\Core\Catalog\CatalogQuery;

/**
 * @method static CatalogQuery forUser(mixed $user)
 * @method static CatalogQuery all()
 * @method static CatalogQuery forArea(string $area)
 * @method static CatalogQuery forMenuKeys(list<string> $menuKeys)
 * @method static CatalogQuery forFeatureKeys(list<string> $featureKeys)
 *
 * CatalogQuery terminals (chain after all(), forArea(), forMenuKeys(), etc.):
 *
 * @method static int revision()
 * @method static list<\Laravarc\Core\Catalog\SurfaceRef> surfaces()
 * @method static list<string> policies(list<\Laravarc\Core\Catalog\SurfaceRef>|null $surfaces = null)
 * @method static array<string, true> policySet(list<\Laravarc\Core\Catalog\SurfaceRef>|null $surfaces = null)
 * @method static list<string> abilities(list<\Laravarc\Core\Catalog\SurfaceRef>|null $surfaces = null)
 * @method static array{by_menu: array<string, list<\Laravarc\Core\Catalog\AbilityRef>>, unassigned: list<\Laravarc\Core\Catalog\AbilityRef>} abilitiesByMenu()
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
