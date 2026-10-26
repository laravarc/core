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
 * CatalogQuery terminals (chain after all(), forArea(), etc.):
 *
 * @method static int revision()
 * @method static list<\Laravarc\Core\Catalog\SurfaceRef> surfaceRefs()
 * @method static list<string> policyKeys()
 * @method static list<string> policyKeysForSurfaces(list<\Laravarc\Core\Catalog\SurfaceRef> $surfaces)
 * @method static array<string, true> policyKeySetForSurfaces(list<\Laravarc\Core\Catalog\SurfaceRef> $surfaces)
 * @method static list<string> abilityCompoundsToGrant(list<\Laravarc\Core\Catalog\SurfaceRef> $surfaces)
 * @method static list<string> abilityCompoundsToGrantFromMenuAndFeatureKeys(list<string> $menuKeys, list<string> $featureKeys)
 * @method static list<string> abilityCompoundsForCeiling(list<\Laravarc\Core\Catalog\SurfaceRef> $surfaces)
 * @method static list<string> abilityCompoundsForCeilingFromMenuKeys(list<string> $menuKeys)
 * @method static list<string> abilityCompoundsForCeilingFromFeatureKeys(list<string> $featureKeys)
 * @method static list<string> abilityCompoundsForCeilingFromMenuAndFeatureKeys(list<string> $menuKeys, list<string> $featureKeys)
 * @method static array{by_menu: array<string, list<\Laravarc\Core\Catalog\AbilityRef>>, unassigned: list<\Laravarc\Core\Catalog\AbilityRef>} groupedAbilityRefs()
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
