<?php

declare(strict_types=1);

use Laravarc\Core\Catalog\CatalogAuthorizationIndex;
use Laravarc\Core\Catalog\CatalogManager;
use Laravarc\Core\Facades\Catalog;
use Laravarc\Core\Metadata\MetadataArtifact;
use Laravarc\Core\Tests\Fixtures\FakeAbilityCatalog;
use Laravarc\Core\Tests\Fixtures\FakeMetadataReader;

describe('Catalog abilities() characterization', function () {
    it('returns first OR-group ability keys only via forMenuKeys chain', function () {
        $controller = 'App\\Modules\\Demo\\Controllers\\GateController';

        $metadata = new FakeMetadataReader(new MetadataArtifact(modules: [
            'demo' => [
                'controllers' => [
                    'gate' => [
                        'controller' => $controller,
                        'menu' => ['key' => 'demo.menu', 'label' => 'Demo'],
                    ],
                ],
            ],
        ]));

        $abilities = new FakeAbilityCatalog(
            gates: [
                $controller => [
                    'index' => [
                        [
                            'demo.policies.alpha.viewAny',
                            'demo.policies.alpha.update',
                        ],
                        [
                            'demo.policies.beta.delete',
                        ],
                    ],
                ],
            ],
        );

        $index = new CatalogAuthorizationIndex($metadata, $abilities);

        app()->instance(CatalogAuthorizationIndex::class, $index);
        app()->forgetInstance(CatalogManager::class);
        Catalog::clearResolvedInstance(CatalogManager::class);

        $keys = Catalog::forMenuKeys(['demo.menu'])->abilities();

        expect($keys)->toBe([
            'demo.policies.alpha.update',
            'demo.policies.alpha.viewAny',
        ])->and($keys)->not->toContain('demo.policies.beta.delete');

        $tree = Catalog::all()->authorization()->tree()->controllers();
        expect($tree[$controller]['index'])->toHaveCount(2);
    });
});
