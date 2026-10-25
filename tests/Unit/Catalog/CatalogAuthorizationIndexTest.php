<?php

declare(strict_types=1);

use Laravarc\Core\Catalog\AbilityRef;
use Laravarc\Core\Catalog\CatalogAuthorizationIndex;
use Laravarc\Core\Catalog\SurfaceRef;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;
use Laravarc\Core\Metadata\MetadataArtifact;
use Laravarc\Core\Tests\Fixtures\FakeAbilityCatalog;
use Laravarc\Core\Tests\Fixtures\FakeMetadataReader;

describe('CatalogAuthorizationIndex', function () {
    it('keeps every OR-group on tree() but returns only the first OR-group from abilitiesForSurfaces()', function () {
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
        $tree = $index->tree()->controllers();

        expect($tree[$controller]['index'])->toHaveCount(2)
            ->and($tree[$controller]['index'][0])->toHaveCount(2)
            ->and($tree[$controller]['index'][0][0])->toBeInstanceOf(AbilityRef::class)
            ->and($tree[$controller]['index'][0][0]->policyKey)->toBe('demo.policies.alpha')
            ->and($tree[$controller]['index'][1][0]->policyKey)->toBe('demo.policies.beta');

        $surfaceAbilities = $index->abilitiesForSurfaces([SurfaceRef::menu('demo.menu')]);

        expect($surfaceAbilities)->toHaveCount(2)
            ->and($surfaceAbilities[0]->ability)->toBe('viewAny')
            ->and($surfaceAbilities[1]->ability)->toBe('update')
            ->and(collect($surfaceAbilities)->contains(fn (AbilityRef $ref): bool => $ref->ability === 'delete'))->toBeFalse();
    });

    it('throws on duplicate feature keys and writes no destination file', function () {
        $controller = 'App\\Modules\\Demo\\Controllers\\DupController';
        $path = sys_get_temp_dir().'/arc-auth-dup-'.uniqid('', true).'.php';

        $metadata = new FakeMetadataReader(new MetadataArtifact(modules: [
            'demo' => [
                'controllers' => [
                    'dup' => [
                        'controller' => $controller,
                        'features' => [
                            ['key' => 'dup.feature', 'label' => 'One'],
                            ['key' => 'dup.feature', 'label' => 'Two'],
                        ],
                    ],
                ],
            ],
        ]));

        $index = new CatalogAuthorizationIndex(
            metadata: $metadata,
            abilities: new FakeAbilityCatalog(),
            path: $path,
        );

        expect(fn () => $index->write($path))
            ->toThrow(MetadataCompileException::class, 'duplicate feature key')
            ->and(is_file($path))->toBeFalse();
    });

    it('removes the temp file when publish fails and leaves the destination unchanged', function () {
        $controller = 'App\\Modules\\Demo\\Controllers\\WriteController';
        $directory = sys_get_temp_dir().'/arc-auth-write-'.uniqid('', true);
        mkdir($directory, 0777, true);
        $path = $directory.'/catalog-authorization.php';

        file_put_contents($path, "<?php\n\nreturn ['schema_version' => 1, 'surfaces' => [], 'trees' => [], 'policy_surfaces' => []];\n");
        $originalContents = file_get_contents($path);

        $metadata = new FakeMetadataReader(new MetadataArtifact(modules: [
            'demo' => [
                'controllers' => [
                    'write' => [
                        'controller' => $controller,
                        'menu' => ['key' => 'write.menu', 'label' => 'Write'],
                    ],
                ],
            ],
        ]));

        $index = new CatalogAuthorizationIndex(
            metadata: $metadata,
            abilities: new FakeAbilityCatalog(
                gates: [
                    $controller => [
                        'index' => [['demo.policies.widget.viewAny']],
                    ],
                ],
            ),
            path: $path,
        );

        @unlink($path);
        mkdir($path, 0777, true);

        expect(fn () => $index->write($path))
            ->toThrow(MetadataCompileException::class, 'Unable to publish');

        expect(is_dir($path))->toBeTrue()
            ->and(glob($directory.'/*.tmp'))->toBe([]);

        @rmdir($path);
        file_put_contents($path, $originalContents ?: "<?php\n\nreturn [];\n");
    });
});
