<?php

declare(strict_types=1);

use Laravarc\Core\Catalog\AbilityRef;
use Laravarc\Core\Catalog\SurfaceRef;
use Laravarc\Core\Facades\Catalog;

describe('CatalogQuery ergonomics terminals', function () {
    it('exposes revision from the catalog state', function () {
        expect(Catalog::all()->revision())->toBeInt();
    });

    it('builds surface refs from separate menu and feature key lists', function () {
        $refs = [
            ...SurfaceRef::menus(['a.menu']),
            ...SurfaceRef::features(['b.feature']),
        ];

        expect($refs)->toHaveCount(2)
            ->and($refs[0]->kind)->toBe(SurfaceRef::KIND_MENU)
            ->and($refs[0]->key)->toBe('a.menu')
            ->and($refs[1]->kind)->toBe(SurfaceRef::KIND_FEATURE)
            ->and($refs[1]->key)->toBe('b.feature');
    });

    it('sorts unique ability keys', function () {
        $refs = [
            new AbilityRef('demo.policies.alpha', 'update'),
            new AbilityRef('demo.policies.alpha', 'viewAny'),
            new AbilityRef('demo.policies.alpha', 'viewAny'),
        ];

        expect(AbilityRef::sortedAbilityKeys($refs))->toBe([
            'demo.policies.alpha.update',
            'demo.policies.alpha.viewAny',
        ]);
    });

    it('returns empty policy terminals for empty surfaces', function () {
        expect(Catalog::all()->policies([]))->toBe([])
            ->and(Catalog::all()->policySet([]))->toBe([]);
    });

    it('returns empty abilities when no surfaces are chained or passed', function () {
        expect(Catalog::all()->abilities())->toBe([])
            ->and(Catalog::forMenuKeys([])->forFeatureKeys([])->abilities())->toBe([]);
    });
});
