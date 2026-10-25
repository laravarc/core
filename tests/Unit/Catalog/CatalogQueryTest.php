<?php

declare(strict_types=1);

use Illuminate\Support\Traits\Macroable;
use Laravarc\Core\Catalog\Catalog;
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
        expect(method_exists(Catalog::class, 'forPolicy'))->toBeFalse()
            ->and(method_exists(Catalog::class, 'forUser'))->toBeTrue()
            ->and(method_exists(Catalog::class, 'all'))->toBeTrue();
    });
});
