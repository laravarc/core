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
        $refs = SurfaceRef::fromMenuAndFeatureKeys(['a.menu'], ['b.feature']);

        expect($refs)->toHaveCount(2)
            ->and($refs[0]->kind)->toBe(SurfaceRef::KIND_MENU)
            ->and($refs[0]->key)->toBe('a.menu')
            ->and($refs[1]->kind)->toBe(SurfaceRef::KIND_FEATURE)
            ->and($refs[1]->key)->toBe('b.feature');
    });

    it('sorts unique ability compounds', function () {
        $refs = [
            new AbilityRef('demo.policies.alpha', 'update'),
            new AbilityRef('demo.policies.alpha', 'viewAny'),
            new AbilityRef('demo.policies.alpha', 'viewAny'),
        ];

        expect(AbilityRef::toSortedCompounds($refs))->toBe([
            'demo.policies.alpha.update',
            'demo.policies.alpha.viewAny',
        ]);
    });

    it('returns empty policy terminals for empty surfaces', function () {
        expect(Catalog::all()->policyKeysForSurfaces([]))->toBe([])
            ->and(Catalog::all()->policyKeySetForSurfaces([]))->toBe([]);
    });

    it('returns empty ceiling terminals for empty menu or feature key lists', function () {
        expect(Catalog::all()->abilityCompoundsForCeilingFromMenuKeys([]))->toBe([])
            ->and(Catalog::all()->abilityCompoundsForCeilingFromFeatureKeys([]))->toBe([])
            ->and(Catalog::all()->abilityCompoundsForCeilingFromMenuAndFeatureKeys([], []))->toBe([])
            ->and(Catalog::all()->abilityCompoundsToGrantFromMenuAndFeatureKeys([], []))->toBe([]);
    });
});
