<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Contracts\CatalogReader;

final class CatalogManager
{
    public function __construct(
        private readonly CatalogReader $reader,
        private readonly CatalogState $state,
        private readonly CatalogManifestBuilder $manifestBuilder,
        private readonly CatalogMetadataSurfaceIndex $surfaceIndex,
        private readonly CatalogUserVisibility $visibility,
        private readonly AbilityCatalog $abilities,
        private readonly CatalogAuthorizationIndex $authorizationIndex,
    ) {}

    public function forUser(mixed $user): CatalogQuery
    {
        return new CatalogQuery(
            reader: $this->reader,
            state: $this->state,
            manifestBuilder: $this->manifestBuilder,
            surfaceIndex: $this->surfaceIndex,
            visibility: $this->visibility,
            abilities: $this->abilities,
            authorizationIndex: $this->authorizationIndex,
            user: $user,
            applyUserVisibility: true,
        );
    }

    public function all(): CatalogQuery
    {
        return new CatalogQuery(
            reader: $this->reader,
            state: $this->state,
            manifestBuilder: $this->manifestBuilder,
            surfaceIndex: $this->surfaceIndex,
            visibility: $this->visibility,
            abilities: $this->abilities,
            authorizationIndex: $this->authorizationIndex,
        );
    }

    public function query(): CatalogQuery
    {
        return $this->all();
    }

    public function forArea(string $area): CatalogQuery
    {
        return $this->all()->forArea($area);
    }
}
