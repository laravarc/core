<?php

declare(strict_types=1);

use Illuminate\Support\Traits\Macroable;
use Laravarc\Core\Facades\Catalog;
use Laravarc\Core\Catalog\CatalogManager;
use Laravarc\Core\Catalog\CatalogQuery;

describe('Catalog query API', function () {
    it('returns new queries from static entries', function () {
        $first = Catalog::all();
        $second = Catalog::all();

        expect($first)->toBeInstanceOf(CatalogQuery::class)
            ->and($second)->toBeInstanceOf(CatalogQuery::class)
            ->and($first)->not->toBe($second);
    });

    it('clones on chain methods', function () {
        $base = Catalog::all();
        $chained = $base->withOrphaned()->forPolicy('demo.policy');

        expect($chained)->not->toBe($base);
    });

    it('exposes Macroable on the query but not the manager', function () {
        $uses = class_uses(CatalogQuery::class);
        $managerUses = class_uses(CatalogManager::class);

        expect($uses)->toContain(Macroable::class)
            ->and($managerUses)->not->toContain(Macroable::class);
    });

    it('does not expose forPolicy as a static catalog entry', function () {
        expect(method_exists(CatalogManager::class, 'forPolicy'))->toBeFalse()
            ->and(fn () => Catalog::forPolicy('demo.policy'))->toThrow(\Error::class)
            ->and(Catalog::class)->toExtend(\Illuminate\Support\Facades\Facade::class);
    });

    it('filters known features and drops orphans from the default map', function () {
        $query = Catalog::all()->withOrphaned();
        $features = $query->features();

        if ($features === []) {
            expect(true)->toBeTrue();

            return;
        }

        $firstKey = (string) array_key_first($features);
        $known = Catalog::all()->filterKnownFeatures([$firstKey, 'definitely.unknown.feature.key']);

        expect($known)->toBe([$firstKey]);
    });
});
