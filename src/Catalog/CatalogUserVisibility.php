<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Contracts\CatalogReader;

final class CatalogUserVisibility
{
    public function __construct(
        private readonly AbilityCatalog $abilities,
        private readonly CatalogReader $reader,
    ) {}

    public function menuVisibleTo(mixed $user, string $menuKey): bool
    {
        if ($user !== null && is_object($user) && method_exists($user, 'isSuper') && $user->isSuper()) {
            return true;
        }

        foreach ($this->reader->controllersForMenu($menuKey) as $controller) {
            if ($this->controllerVisibleTo($user, $controller)) {
                return true;
            }
        }

        return false;
    }

    public function featureVisibleTo(mixed $user, string $featureKey): bool
    {
        if ($user !== null && is_object($user) && method_exists($user, 'isSuper') && $user->isSuper()) {
            return true;
        }

        $menuKey = $this->reader->menuKeyForFeature($featureKey);

        if ($menuKey !== null) {
            foreach ($this->reader->controllersForMenu($menuKey) as $controller) {
                if ($this->controllerVisibleTo($user, $controller)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function controllerVisibleTo(mixed $user, string $controller): bool
    {
        if (! is_object($user) || ! method_exists($user, 'hasAbility')) {
            return false;
        }

        foreach ($this->abilities->gates()[$controller] ?? [] as $groups) {
            foreach ($groups as $group) {
                if ($this->groupPasses($user, $group)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $abilities
     */
    private function groupPasses(object $user, array $abilities): bool
    {
        if ($abilities === []) {
            return false;
        }

        foreach ($abilities as $ability) {
            if (! $user->hasAbility($ability)) {
                return false;
            }
        }

        return true;
    }
}
