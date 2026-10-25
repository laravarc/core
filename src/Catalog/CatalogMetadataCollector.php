<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Contracts\MetadataReader;

final class CatalogMetadataCollector
{
    public function __construct(
        private readonly MetadataReader $metadata,
    ) {}

    /**
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    public function collect(): array
    {
        $menusFromMeta = [];
        $featuresFromMeta = [];

        foreach ($this->metadata->artifact()->modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            foreach ($module['controllers'] ?? [] as $controller) {
                if (! is_array($controller)) {
                    continue;
                }

                $menu = is_array($controller['menu'] ?? null) ? $controller['menu'] : null;

                if ($menu !== null && ! empty($menu['key'])) {
                    $menusFromMeta[(string) $menu['key']] = $menu;
                }

                foreach ($controller['features'] ?? [] as $feature) {
                    if (! is_array($feature) || empty($feature['key'])) {
                        continue;
                    }

                    $menuKey = isset($feature['menu']) && is_string($feature['menu']) && $feature['menu'] !== ''
                        ? $feature['menu']
                        : null;

                    $featuresFromMeta[(string) $feature['key']] = [...$feature, '_menu_key' => $menuKey];
                }
            }
        }

        return [$menusFromMeta, $featuresFromMeta];
    }
}
