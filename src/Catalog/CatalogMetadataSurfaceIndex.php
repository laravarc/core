<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Contracts\MetadataReader;

final class CatalogMetadataSurfaceIndex
{
    /** @var array<string, array{policies: list<string>, models: list<string>}>|null */
    private ?array $menuIndex = null;

    /** @var array<string, array{policies: list<string>, models: list<string>}>|null */
    private ?array $featureIndex = null;

    public function __construct(
        private readonly MetadataReader $metadata,
        private readonly AbilityCatalog $abilities,
    ) {}

    /**
     * @return array{policies: list<string>, models: list<string>}
     */
    public function forMenu(string $menuKey): array
    {
        return $this->menuIndex()[$menuKey] ?? ['policies' => [], 'models' => []];
    }

    /**
     * @return array{policies: list<string>, models: list<string>}
     */
    public function forFeature(string $featureKey): array
    {
        return $this->featureIndex()[$featureKey] ?? ['policies' => [], 'models' => []];
    }

    /**
     * @return array<string, array{policies: list<string>, models: list<string>}>
     */
    private function menuIndex(): array
    {
        if ($this->menuIndex !== null) {
            return $this->menuIndex;
        }

        $index = [];

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

                if ($menu === null || ! isset($menu['key']) || ! is_string($controllerClass) || $controllerClass === '') {
                    continue;
                }

                $menuKey = (string) $menu['key'];
                $identifiers = $this->identifiersForController($controllerClass);
                $index[$menuKey]['policies'] = array_values(array_unique([
                    ...($index[$menuKey]['policies'] ?? []),
                    ...$identifiers['policies'],
                ]));
                $index[$menuKey]['models'] = array_values(array_unique([
                    ...($index[$menuKey]['models'] ?? []),
                    ...$identifiers['models'],
                ]));
            }
        }

        return $this->menuIndex = $index;
    }

    /**
     * @return array<string, array{policies: list<string>, models: list<string>}>
     */
    private function featureIndex(): array
    {
        if ($this->featureIndex !== null) {
            return $this->featureIndex;
        }

        $index = [];

        foreach ($this->metadata->artifact()->modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            foreach ($module['controllers'] ?? [] as $controller) {
                if (! is_array($controller)) {
                    continue;
                }

                $controllerClass = $controller['controller'] ?? null;

                if (! is_string($controllerClass) || $controllerClass === '') {
                    continue;
                }

                $identifiers = $this->identifiersForController($controllerClass);

                foreach ($controller['features'] ?? [] as $feature) {
                    if (! is_array($feature) || empty($feature['key'])) {
                        continue;
                    }

                    $featureKey = (string) $feature['key'];
                    $index[$featureKey]['policies'] = array_values(array_unique([
                        ...($index[$featureKey]['policies'] ?? []),
                        ...$identifiers['policies'],
                    ]));
                    $index[$featureKey]['models'] = array_values(array_unique([
                        ...($index[$featureKey]['models'] ?? []),
                        ...$identifiers['models'],
                    ]));
                }
            }
        }

        return $this->featureIndex = $index;
    }

    /**
     * @return array{policies: list<string>, models: list<string>}
     */
    private function identifiersForController(string $controllerClass): array
    {
        $policies = [];
        $models = [];

        foreach ($this->abilities->gates()[$controllerClass] ?? [] as $groups) {
            foreach ($groups as $group) {
                foreach ($group as $compound) {
                    if (! is_string($compound) || $compound === '') {
                        continue;
                    }

                    $pos = strrpos($compound, '.');

                    if ($pos === false) {
                        continue;
                    }

                    $policyKey = substr($compound, 0, $pos);
                    $policies[$policyKey] = true;

                    $policy = $this->abilities->policies()[$policyKey] ?? null;

                    if (is_array($policy)) {
                        foreach ($policy['models'] ?? [] as $model) {
                            if (is_array($model) && isset($model['class']) && is_string($model['class'])) {
                                $models[$model['class']] = true;
                            }
                        }
                    }
                }
            }
        }

        return [
            'policies' => array_keys($policies),
            'models' => array_keys($models),
        ];
    }
}
