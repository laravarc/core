<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravarc\Core\Catalog\Models\CatalogFeature;
use Laravarc\Core\Catalog\Models\CatalogFeatureTranslation;
use Laravarc\Core\Catalog\Models\CatalogMenu;
use Laravarc\Core\Catalog\Models\CatalogMenuTranslation;
use Laravarc\Core\Support\CachePath;

final class CatalogSynchronizer
{
    public const SOURCE_METADATA = 'metadata';

    public const SOURCE_CUSTOM = 'custom';

    public function __construct(
        private readonly CatalogMetadataCollector $collector,
        private readonly CatalogState $state,
        private readonly CatalogAuthorizationIndex $authorizationIndex,
    ) {}

    /**
     * @return array{inserted: int, updated: int, orphaned: int, restored: int, warnings: list<string>}
     */
    public function sync(): array
    {
        [$menusFromMeta, $featuresFromMeta] = $this->collector->collect();

        $stats = [
            'inserted' => 0,
            'updated' => 0,
            'orphaned' => 0,
            'restored' => 0,
            'warnings' => [],
        ];

        try {
            DB::transaction(function () use ($menusFromMeta, $featuresFromMeta, &$stats): void {
                foreach ($menusFromMeta as $key => $menu) {
                    $this->syncMenu((string) $key, $menu, $stats);
                }

                $stats['orphaned'] += $this->orphanMissingMenus(array_keys($menusFromMeta), $stats);
                $stats['restored'] += $this->countRestoredMenus($menusFromMeta);

                foreach ($featuresFromMeta as $key => $feature) {
                    $this->syncFeature((string) $key, $feature, $stats);
                }

                $stats['orphaned'] += $this->orphanMissingFeatures(array_keys($featuresFromMeta), $stats);
            });
        } catch (QueryException $exception) {
            throw $exception;
        }

        if ($this->state->isInstalled()) {
            $this->state->bumpRevision();
        }

        $this->authorizationIndex->write(CachePath::authorization());

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $menu
     * @param  array{inserted: int, updated: int, orphaned: int, restored: int, warnings: list<string>}  $stats
     */
    private function syncMenu(string $key, array $menu, array &$stats): void
    {
        $existing = CatalogMenu::query()->where('key', $key)->first();

        if ($existing === null) {
            $row = CatalogMenu::query()->create([
                'key' => $key,
                'area' => $this->metadataArea($menu),
                'parent_key' => isset($menu['parent']) ? (string) $menu['parent'] : null,
                'source' => self::SOURCE_METADATA,
                'icon' => isset($menu['icon']) ? (string) $menu['icon'] : null,
                'path' => $this->metadataPath($menu),
                'path_locked' => false,
                'order' => (int) ($menu['order'] ?? 0),
                'order_locked' => false,
                'status' => 'enabled',
                'is_orphaned' => false,
                'orphaned_at' => null,
            ]);
            $this->seedMenuTranslation($row, (string) ($menu['label'] ?? $key));
            $stats['inserted']++;

            return;
        }

        if ($existing->source === self::SOURCE_CUSTOM) {
            return;
        }

        $dirty = false;

        if (! $existing->order_locked) {
            $order = (int) ($menu['order'] ?? 0);
            if ($existing->order !== $order) {
                $existing->order = $order;
                $dirty = true;
            }
        }

        if (! $existing->path_locked) {
            $path = $this->metadataPath($menu);
            if ($existing->path !== $path) {
                $existing->path = $path;
                $dirty = true;
            }
        }

        if (! $existing->order_locked && isset($menu['parent'])) {
            $parent = (string) $menu['parent'];
            if ($existing->parent_key !== $parent) {
                $existing->parent_key = $parent;
                $dirty = true;
            }
        }

        $area = $this->metadataArea($menu);
        if ($existing->area !== $area) {
            $existing->area = $area;
            $dirty = true;
        }

        if ($existing->is_orphaned) {
            $existing->is_orphaned = false;
            $existing->orphaned_at = null;
            $stats['restored']++;
            $dirty = true;
        }

        if ($dirty) {
            $existing->save();
            $stats['updated']++;
        }
    }

    /**
     * @param  array<string, mixed>  $feature
     * @param  array{inserted: int, updated: int, orphaned: int, restored: int, warnings: list<string>}  $stats
     */
    private function syncFeature(string $key, array $feature, array &$stats): void
    {
        $menuKey = $feature['_menu_key'] ?? null;
        $menuKey = is_string($menuKey) && $menuKey !== '' ? $menuKey : null;

        $existing = CatalogFeature::query()->where('key', $key)->first();

        if ($existing === null) {
            $row = CatalogFeature::query()->create([
                'key' => $key,
                'area' => $this->metadataArea($feature),
                'menu_id' => $this->resolveMenuId($menuKey),
                'source' => self::SOURCE_METADATA,
                'path' => isset($feature['path']) ? (string) $feature['path'] : null,
                'path_locked' => false,
                'placement' => isset($feature['placement']) ? (string) $feature['placement'] : null,
                'order' => (int) ($feature['order'] ?? 0),
                'order_locked' => false,
                'status' => 'enabled',
                'is_orphaned' => false,
                'orphaned_at' => null,
            ]);
            $this->seedFeatureTranslation($row, (string) ($feature['label'] ?? $key));
            $stats['inserted']++;

            return;
        }

        if ($existing->source === self::SOURCE_CUSTOM) {
            return;
        }

        $dirty = false;

        if (! $existing->order_locked) {
            $order = (int) ($feature['order'] ?? 0);
            if ($existing->order !== $order) {
                $existing->order = $order;
                $dirty = true;
            }
        }

        if (! $existing->path_locked) {
            $path = isset($feature['path']) ? (string) $feature['path'] : null;
            if ($existing->path !== $path) {
                $existing->path = $path;
                $dirty = true;
            }
        }

        $menuId = $this->resolveMenuId($menuKey);
        if ($existing->menu_id !== $menuId) {
            $existing->menu_id = $menuId;
            $dirty = true;
        }

        $area = $this->metadataArea($feature);
        if ($existing->area !== $area) {
            $existing->area = $area;
            $dirty = true;
        }

        if ($existing->is_orphaned) {
            $existing->is_orphaned = false;
            $existing->orphaned_at = null;
            $stats['restored']++;
            $dirty = true;
        }

        if ($dirty) {
            $existing->save();
            $stats['updated']++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function metadataArea(array $row): ?string
    {
        $area = $row['area'] ?? null;

        return is_string($area) && $area !== '' ? $area : null;
    }

    /**
     * @param  list<string>  $metadataKeys
     * @param  array{inserted: int, updated: int, orphaned: int, restored: int, warnings: list<string>}  $stats
     */
    private function orphanMissingMenus(array $metadataKeys, array &$stats): int
    {
        $wanted = array_fill_keys($metadataKeys, true);
        $orphaned = 0;

        CatalogMenu::query()
            ->where('source', self::SOURCE_METADATA)
            ->where('is_orphaned', false)
            ->get()
            ->each(function (CatalogMenu $menu) use ($wanted, &$orphaned): void {
                if (isset($wanted[$menu->key])) {
                    return;
                }

                $menu->is_orphaned = true;
                $menu->orphaned_at = $menu->orphaned_at ?? now();
                $menu->save();
                $orphaned++;
            });

        return $orphaned;
    }

    /**
     * @param  list<string>  $metadataKeys
     * @param  array{inserted: int, updated: int, orphaned: int, restored: int, warnings: list<string>}  $stats
     */
    private function orphanMissingFeatures(array $metadataKeys, array &$stats): int
    {
        $wanted = array_fill_keys($metadataKeys, true);
        $orphaned = 0;

        CatalogFeature::query()
            ->where('source', self::SOURCE_METADATA)
            ->where('is_orphaned', false)
            ->get()
            ->each(function (CatalogFeature $feature) use ($wanted, &$orphaned): void {
                if (isset($wanted[$feature->key])) {
                    return;
                }

                $feature->is_orphaned = true;
                $feature->orphaned_at = $feature->orphaned_at ?? now();
                $feature->save();
                $orphaned++;
            });

        return $orphaned;
    }

    /**
     * @param  array<string, array<string, mixed>>  $menusFromMeta
     */
    private function countRestoredMenus(array $menusFromMeta): int
    {
        return 0;
    }

    /**
     * @param  array<string, mixed>  $menu
     */
    private function metadataPath(array $menu): ?string
    {
        if (! isset($menu['path'])) {
            return null;
        }

        $path = (string) $menu['path'];

        return $path === '' ? null : ltrim($path, '/');
    }

    private function seedMenuTranslation(CatalogMenu $menu, string $label): void
    {
        CatalogMenuTranslation::query()->create([
            'menu_id' => $menu->id,
            'locale' => app()->getLocale(),
            'label' => $label,
        ]);
    }

    private function seedFeatureTranslation(CatalogFeature $feature, string $label): void
    {
        CatalogFeatureTranslation::query()->create([
            'feature_id' => $feature->id,
            'locale' => app()->getLocale(),
            'label' => $label,
        ]);
    }

    private function resolveMenuId(?string $menuKey): ?int
    {
        if ($menuKey === null || $menuKey === '') {
            return null;
        }

        return CatalogMenu::query()->where('key', $menuKey)->value('id');
    }
}
