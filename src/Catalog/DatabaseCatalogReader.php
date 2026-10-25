<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Catalog\Models\CatalogFeature;
use Laravarc\Core\Catalog\Models\CatalogMenu;
use Laravarc\Core\Contracts\CatalogReader;
use Laravarc\Core\Contracts\MetadataReader;

final class DatabaseCatalogReader implements CatalogReader
{
    public function __construct(
        private readonly MetadataReader $metadata,
    ) {}

    public function menus(): array
    {
        $result = [];

        CatalogMenu::query()->with('translations')->orderBy('order')->get()->each(function (CatalogMenu $menu) use (&$result): void {
            $translations = [];
            foreach ($menu->translations as $translation) {
                $translations[$translation->locale] = $translation->label;
            }

            $result[$menu->key] = [
                'key' => $menu->key,
                'label' => $this->defaultLabel($translations, $menu->key),
                'parent_key' => $menu->parent_key,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'order' => $menu->order,
                'status' => $menu->status,
                'source' => $menu->source,
                'translations' => $translations,
                'is_orphaned' => $menu->is_orphaned,
                'orphaned_at' => $menu->orphaned_at?->toIso8601String(),
            ];
        });

        return $result;
    }

    public function features(): array
    {
        $result = [];

        CatalogFeature::query()->with(['translations', 'menu'])->orderBy('order')->get()->each(function (CatalogFeature $feature) use (&$result): void {
            $translations = [];
            foreach ($feature->translations as $translation) {
                $translations[$translation->locale] = $translation->label;
            }

            $result[$feature->key] = [
                'key' => $feature->key,
                'label' => $this->defaultLabel($translations, $feature->key),
                'menu' => $feature->menu?->key,
                'path' => $feature->path,
                'placement' => $feature->placement,
                'order' => $feature->order,
                'status' => $feature->status,
                'source' => $feature->source,
                'translations' => $translations,
                'is_orphaned' => $feature->is_orphaned,
                'orphaned_at' => $feature->orphaned_at?->toIso8601String(),
            ];
        });

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
        $feature = CatalogFeature::query()->with('menu')->where('key', $featureKey)->first();

        if ($feature === null) {
            return null;
        }

        return $feature->menu?->key;
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function defaultLabel(array $translations, string $fallback): string
    {
        $locale = app()->getLocale();

        return $translations[$locale] ?? reset($translations) ?: $fallback;
    }
}
