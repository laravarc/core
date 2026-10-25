<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

interface CatalogReader
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function menus(): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function features(): array;

    /**
     * @return list<string>
     */
    public function controllersForMenu(string $menuKey): array;

    public function menuKeyForFeature(string $featureKey): ?string;
}
