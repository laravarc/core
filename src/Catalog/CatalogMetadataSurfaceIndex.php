<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

final class CatalogMetadataSurfaceIndex
{
    public function __construct(
        private readonly CatalogAuthorizationIndex $authorizationIndex,
    ) {}

    /**
     * @return array{policies: list<string>, models: list<string>}
     */
    public function forMenu(string $menuKey): array
    {
        return $this->authorizationIndex->bindingsForMenu($menuKey);
    }

    /**
     * @return array{policies: list<string>, models: list<string>}
     */
    public function forFeature(string $featureKey): array
    {
        return $this->authorizationIndex->bindingsForFeature($featureKey);
    }
}
