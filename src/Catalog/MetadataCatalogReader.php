<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Contracts\CatalogReader;
use Laravarc\Core\Contracts\MetadataReader;

final class MetadataCatalogReader implements CatalogReader
{
    public function __construct(
        private readonly MetadataReader $metadata,
        private readonly CatalogMetadataCollector $collector,
    ) {}

    public function menus(): array
    {
        [$menusFromMeta] = $this->collector->collect();
        $result = [];

        foreach ($menusFromMeta as $key => $menu) {
            $result[$key] = $this->normalizeMenu($key, $menu);
        }

        return $result;
    }

    public function features(): array
    {
        [, $featuresFromMeta] = $this->collector->collect();
        $result = [];

        foreach ($featuresFromMeta as $key => $feature) {
            $result[$key] = $this->normalizeFeature($key, $feature);
        }

        return $result;
    }

    public function controllersForMenu(string $menuKey): array
    {
        $controllers = [];

        foreach ($this->metadata->artifact()->modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            foreach ($module['controllers'] ?? [] as $controller) {
                if (! is_array($controller)) {
                    continue;
                }

                $menu = is_array($controller['menu'] ?? null) ? $controller['menu'] : null;
                $controllerClass = $controller['controller'] ?? null;

                if ($menu !== null && (string) ($menu['key'] ?? '') === $menuKey && is_string($controllerClass) && $controllerClass !== '') {
                    $controllers[$controllerClass] = true;
                }
            }
        }

        return array_keys($controllers);
    }

    public function menuKeyForFeature(string $featureKey): ?string
    {
        $features = $this->features();
        $menu = $features[$featureKey]['menu'] ?? null;

        return is_string($menu) && $menu !== '' ? $menu : null;
    }

    /**
     * @param  array<string, mixed>  $menu
     * @return array<string, mixed>
     */
    private function normalizeMenu(string $key, array $menu): array
    {
        return [
            'key' => $key,
            'area' => null,
            'label' => (string) ($menu['label'] ?? $key),
            'parent_key' => isset($menu['parent']) ? (string) $menu['parent'] : null,
            'path' => isset($menu['path']) ? (string) $menu['path'] : null,
            'icon' => isset($menu['icon']) ? (string) $menu['icon'] : null,
            'order' => (int) ($menu['order'] ?? 0),
            'status' => ($menu['visible'] ?? null) === false ? 'disabled' : 'enabled',
            'source' => CatalogSynchronizer::SOURCE_METADATA,
            'translations' => [],
            'is_orphaned' => false,
            'orphaned_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function normalizeFeature(string $key, array $feature): array
    {
        $menuKey = $feature['_menu_key'] ?? null;

        return [
            'key' => $key,
            'area' => null,
            'label' => (string) ($feature['label'] ?? $key),
            'menu' => is_string($menuKey) && $menuKey !== '' ? $menuKey : null,
            'path' => isset($feature['path']) ? (string) $feature['path'] : null,
            'placement' => isset($feature['placement']) ? (string) $feature['placement'] : null,
            'order' => (int) ($feature['order'] ?? 0),
            'status' => 'enabled',
            'source' => CatalogSynchronizer::SOURCE_METADATA,
            'translations' => [],
            'is_orphaned' => false,
            'orphaned_at' => null,
        ];
    }
}
