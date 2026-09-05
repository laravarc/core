<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Laravarc\Core\Metadata\MetadataArtifact;
use Laravarc\Core\Metadata\MetadataArtifactStoreFactory;
use Laravarc\Core\Metadata\Stores\CacheMetadataArtifactStore;
use Laravarc\Core\Metadata\Stores\FileMetadataArtifactStore;
use Laravarc\Core\Metadata\Stores\JsonMetadataArtifactStore;
use Laravarc\Core\Metadata\Stores\NullMetadataArtifactStore;

describe('MetadataArtifactStoreFactory', function () {
    it('creates supported metadata store drivers', function () {
        $factory = new MetadataArtifactStoreFactory;
        $root = sys_get_temp_dir().'/arc-metadata-store-'.uniqid('', true);

        expect($factory->make('file', $root.'/metadata.php', $root.'/metadata.json', Cache::store(), 'arc.metadata.test'))
            ->toBeInstanceOf(FileMetadataArtifactStore::class)
            ->and($factory->make('json', $root.'/metadata.php', $root.'/metadata.json', Cache::store(), 'arc.metadata.test'))
            ->toBeInstanceOf(JsonMetadataArtifactStore::class)
            ->and($factory->make('cache', $root.'/metadata.php', $root.'/metadata.json', Cache::store(), 'arc.metadata.test'))
            ->toBeInstanceOf(CacheMetadataArtifactStore::class)
            ->and($factory->make('null', $root.'/metadata.php', $root.'/metadata.json', Cache::store(), 'arc.metadata.test'))
            ->toBeInstanceOf(NullMetadataArtifactStore::class);
    });
});

describe('NullMetadataArtifactStore', function () {
    it('does not persist artifacts', function () {
        $store = new NullMetadataArtifactStore;

        $store->write(MetadataArtifact::empty());

        expect($store->isPersistent())->toBeFalse()
            ->and($store->read())->toBeNull();
    });
});

describe('JsonMetadataArtifactStore', function () {
    it('writes and reads json metadata artifact', function () {
        $path = sys_get_temp_dir().'/arc-metadata-json-'.uniqid('', true).'/metadata.json';
        $store = new JsonMetadataArtifactStore($path);
        $artifact = new MetadataArtifact(
            modules: ['admin.user' => [
                'menus' => [],
                'features' => [],
                'policy' => [
                    'model' => 'App\\Modules\\Admin\\User\\Models\\User',
                    'policy' => 'App\\Modules\\Admin\\User\\Policies\\UserPolicy',
                    'abilities' => [],
                    'ability_overrides' => [],
                    'controllers' => [],
                ],
            ]],
            compiledAt: '2026-07-07T00:00:00+00:00',
        );

        $store->write($artifact);

        expect($store->read()?->toArray())->toBe($artifact->toArray());
    });
});

describe('CacheMetadataArtifactStore', function () {
    it('writes and reads cache metadata artifact', function () {
        $cacheKey = 'arc.metadata.test.'.uniqid('', true);
        $store = new CacheMetadataArtifactStore(Cache::store(), $cacheKey);
        $artifact = MetadataArtifact::empty();

        $store->write($artifact);

        expect($store->read()?->toArray())->toBe($artifact->toArray());

        $store->clear();

        expect($store->read())->toBeNull();
    });
});
