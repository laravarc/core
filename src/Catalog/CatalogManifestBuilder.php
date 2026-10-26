<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

final class CatalogManifestBuilder
{
    /**
     * @param  array<string, array<string, mixed>>  $menus
     * @param  array<string, array<string, mixed>>  $features
     * @return array{menus: array<string, array<string, mixed>>, features: array<string, array<string, mixed>>, revision: int}
     */
    public function build(array $menus, array $features, int $revision): array
    {
        $menuResources = [];
        $featureResources = [];

        foreach ($menus as $key => $menu) {
            $childFeatures = [];
            foreach ($features as $featureKey => $feature) {
                if (($feature['menu'] ?? null) === $key) {
                    $childFeatures[] = $featureKey;
                }
            }

            $menuResources[$key] = $this->menuResource($menu, $childFeatures);
        }

        foreach ($features as $key => $feature) {
            $featureResources[$key] = $this->featureResource($feature);
        }

        return [
            'menus' => $menuResources,
            'features' => $featureResources,
            'revision' => $revision,
        ];
    }

    /**
     * @param  array<string, mixed>  $menu
     * @param  list<string>  $childFeatures
     * @return array<string, mixed>
     */
    private function menuResource(array $menu, array $childFeatures): array
    {
        return [
            'key' => (string) $menu['key'],
            'area' => isset($menu['area']) ? (is_string($menu['area']) ? $menu['area'] : null) : null,
            'label' => (string) ($menu['label'] ?? $menu['key']),
            'parent_key' => $menu['parent_key'] ?? null,
            'path' => $menu['path'] ?? null,
            'icon' => $menu['icon'] ?? null,
            'order' => (int) ($menu['order'] ?? 0),
            'status' => (string) ($menu['status'] ?? 'enabled'),
            'source' => (string) ($menu['source'] ?? CatalogSynchronizer::SOURCE_METADATA),
            'translations' => is_array($menu['translations'] ?? null) ? $menu['translations'] : [],
            'features' => $childFeatures,
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function featureResource(array $feature): array
    {
        return [
            'key' => (string) $feature['key'],
            'area' => isset($feature['area']) ? (is_string($feature['area']) ? $feature['area'] : null) : null,
            'label' => (string) ($feature['label'] ?? $feature['key']),
            'menu' => $feature['menu'] ?? null,
            'path' => $feature['path'] ?? null,
            'placement' => $feature['placement'] ?? null,
            'order' => (int) ($feature['order'] ?? 0),
            'status' => (string) ($feature['status'] ?? 'enabled'),
            'source' => (string) ($feature['source'] ?? CatalogSynchronizer::SOURCE_METADATA),
            'translations' => is_array($feature['translations'] ?? null) ? $feature['translations'] : [],
        ];
    }
}
