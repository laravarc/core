<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Catalog\Models\CatalogFeature;
use Laravarc\Core\Catalog\Models\CatalogMenu;

final class CatalogWriter
{
    public function __construct(
        private readonly CatalogState $state,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateMenu(string $key, array $payload): void
    {
        $this->assertInstalled();

        $menu = CatalogMenu::query()->where('key', $key)->firstOrFail();
        $menu->fill($payload);
        $menu->save();
        $this->state->bumpRevision();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateFeature(string $key, array $payload): void
    {
        $this->assertInstalled();

        $feature = CatalogFeature::query()->where('key', $key)->firstOrFail();
        $feature->fill($payload);
        $feature->save();
        $this->state->bumpRevision();
    }

    /**
     * @param  list<array{key: string, order: int}>  $items
     */
    public function reorderMenus(array $items): void
    {
        $this->assertInstalled();

        foreach ($items as $item) {
            CatalogMenu::query()
                ->where('key', $item['key'])
                ->update(['order' => (int) ($item['order'] ?? $item['sort_order'] ?? 0)]);
        }

        $this->state->bumpRevision();
    }

    /**
     * @param  list<array{key: string, order: int}>  $items
     */
    public function reorderFeatures(array $items): void
    {
        $this->assertInstalled();

        foreach ($items as $item) {
            CatalogFeature::query()
                ->where('key', $item['key'])
                ->update(['order' => (int) ($item['order'] ?? $item['sort_order'] ?? 0)]);
        }

        $this->state->bumpRevision();
    }

    public function deleteCustomMenu(string $key): void
    {
        $this->assertInstalled();

        $menu = CatalogMenu::query()->where('key', $key)->firstOrFail();

        if ($menu->source !== CatalogSynchronizer::SOURCE_CUSTOM) {
            throw new CatalogNotInstalledException;
        }

        $menu->delete();
        $this->state->bumpRevision();
    }

    public function deleteCustomFeature(string $key): void
    {
        $this->assertInstalled();

        $feature = CatalogFeature::query()->where('key', $key)->firstOrFail();

        if ($feature->source !== CatalogSynchronizer::SOURCE_CUSTOM) {
            throw new CatalogNotInstalledException;
        }

        $feature->delete();
        $this->state->bumpRevision();
    }

    private function assertInstalled(): void
    {
        if (! $this->state->isInstalled()) {
            throw new CatalogNotInstalledException;
        }
    }
}
