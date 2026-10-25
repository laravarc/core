<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Contracts\CatalogReader;

final class SwitchingCatalogReader implements CatalogReader
{
    public function __construct(
        private readonly CatalogState $state,
        private readonly MetadataCatalogReader $metadataReader,
        private readonly DatabaseCatalogReader $databaseReader,
    ) {}

    public function menus(): array
    {
        return $this->active()->menus();
    }

    public function features(): array
    {
        return $this->active()->features();
    }

    public function controllersForMenu(string $menuKey): array
    {
        return $this->metadataReader->controllersForMenu($menuKey);
    }

    public function menuKeyForFeature(string $featureKey): ?string
    {
        if ($this->state->isInstalled()) {
            return $this->databaseReader->menuKeyForFeature($featureKey);
        }

        return $this->metadataReader->menuKeyForFeature($featureKey);
    }

    private function active(): CatalogReader
    {
        return $this->state->isInstalled() ? $this->databaseReader : $this->metadataReader;
    }
}
