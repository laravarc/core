<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Macroable;
use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Contracts\CatalogReader;

final class CatalogQuery
{
    use Macroable;

    private ?string $policyConstraint = null;

    private ?string $modelConstraint = null;

    private bool $withOrphaned = false;

    private bool $onlyOrphaned = false;

    private ?string $areaConstraint = null;

    public function __construct(
        private readonly CatalogReader $reader,
        private readonly CatalogState $state,
        private readonly CatalogManifestBuilder $manifestBuilder,
        private readonly CatalogMetadataSurfaceIndex $surfaceIndex,
        private readonly CatalogUserVisibility $visibility,
        private readonly AbilityCatalog $abilities,
        private readonly CatalogAuthorizationIndex $authorizationIndex,
        private readonly mixed $user = null,
        private readonly bool $applyUserVisibility = false,
    ) {}

    public function authorization(): CatalogAuthorizationIndex
    {
        return $this->authorizationIndex;
    }

    public function forPolicy(string $policyClassOrKey): self
    {
        $clone = clone $this;
        $clone->policyConstraint = $policyClassOrKey;

        return $clone;
    }

    public function forModel(string $model): self
    {
        $clone = clone $this;
        $clone->modelConstraint = $model;

        return $clone;
    }

    public function forArea(string $area): self
    {
        $clone = clone $this;
        $clone->areaConstraint = $area;

        return $clone;
    }

    public function withOrphaned(): self
    {
        $clone = clone $this;
        $clone->withOrphaned = true;

        return $clone;
    }

    public function onlyOrphaned(): self
    {
        $clone = clone $this;
        $clone->onlyOrphaned = true;
        $clone->withOrphaned = true;

        return $clone;
    }

    /**
     * @return array{menus: array<string, array<string, mixed>>, features: array<string, array<string, mixed>>, revision: int}
     */
    public function manifest(): array
    {
        return Cache::remember($this->cacheKey(), 3600, function (): array {
            [$menus, $features] = $this->filteredRows();

            return $this->manifestBuilder->build($menus, $features, $this->state->revision());
        });
    }

    /**
     * @return array{menus: array<string, array<string, mixed>>, features: array<string, array<string, mixed>>}
     */
    public function resources(): array
    {
        $manifest = $this->manifest();

        return [
            'menus' => $manifest['menus'],
            'features' => $manifest['features'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function menus(): array
    {
        return $this->manifest()['menus'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function features(?string $menuKey = null): array
    {
        $features = $this->manifest()['features'];

        if ($menuKey === null) {
            return $features;
        }

        return array_filter(
            $features,
            static fn (array $feature): bool => ($feature['menu'] ?? null) === $menuKey,
        );
    }

    /**
     * @param  list<string>  $featureKeys
     * @return list<string>
     */
    public function filterKnownFeatures(array $featureKeys): array
    {
        $known = $this->features();

        return array_values(array_filter(
            $featureKeys,
            static fn (mixed $key): bool => is_string($key) && $key !== '' && isset($known[$key]),
        ));
    }

    /**
     * @param  list<string>  $featureKeys
     * @return list<string>
     */
    public function unknownFeatures(array $featureKeys): array
    {
        $known = $this->features();

        return array_values(array_filter(
            $featureKeys,
            static fn (mixed $key): bool => is_string($key) && $key !== '' && ! isset($known[$key]),
        ));
    }

    /**
     * @param  list<string>  $featureKeys
     * @return array<string, string|null>
     */
    public function menuKeysForFeatures(array $featureKeys): array
    {
        $known = $this->features();
        $result = [];

        foreach ($featureKeys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (! isset($known[$key])) {
                continue;
            }

            $menu = $known[$key]['menu'] ?? null;
            $result[$key] = is_string($menu) && $menu !== '' ? $menu : null;
        }

        return $result;
    }

    public function revision(): int
    {
        return $this->state->revision();
    }

    /**
     * @return list<SurfaceRef>
     */
    public function surfaceRefs(): array
    {
        $manifest = $this->manifest();

        return SurfaceRef::fromMenuAndFeatureKeys(
            array_keys($manifest['menus']),
            array_keys($manifest['features']),
        );
    }

    /**
     * Policy keys for every menu and feature in the current query scope (e.g. after forArea).
     *
     * @return list<string>
     */
    public function policyKeys(): array
    {
        $identifiers = $this->authorization()->identifiersForSurfaces($this->surfaceRefs());

        return $identifiers['policies'];
    }

    /**
     * @param  list<SurfaceRef>  $surfaces
     * @return list<string>
     */
    public function abilityCompoundsToGrant(array $surfaces): array
    {
        if ($surfaces === []) {
            return [];
        }

        return AbilityRef::toSortedCompounds(
            $this->authorization()->abilitiesToGrant($surfaces),
        );
    }

    /**
     * @param  list<SurfaceRef>  $surfaces
     * @return list<string>
     */
    public function abilityCompoundsForCeiling(array $surfaces): array
    {
        if ($surfaces === []) {
            return [];
        }

        return AbilityRef::toSortedCompounds(
            $this->authorization()->abilityCeiling($surfaces),
        );
    }

    /**
     * @return array{by_menu: array<string, list<AbilityRef>>, unassigned: list<AbilityRef>}
     */
    public function groupedAbilityRefs(): array
    {
        $manifest = $this->manifest();

        return $this->authorization()->groupedByMenuKey(
            array_keys($manifest['menus']),
            array_keys($manifest['features']),
        );
    }

    /**
     * @return array{menus: array<string, array<string, mixed>>, features: array<string, array<string, mixed>>, revision: int}
     */
    public function toArray(): array
    {
        return $this->manifest();
    }

    /**
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function filteredRows(): array
    {
        $menus = $this->reader->menus();
        $features = $this->reader->features();

        $menus = $this->filterOrphans($menus);
        $features = $this->filterOrphans($features);

        if ($this->areaConstraint !== null) {
            $menus = array_filter(
                $menus,
                fn (array $row): bool => $this->rowVisibleInArea($row),
            );
            $features = array_filter(
                $features,
                fn (array $row): bool => $this->rowVisibleInArea($row),
            );
        }

        if ($this->applyUserVisibility) {
            $menus = array_filter(
                $menus,
                fn (array $menu): bool => $this->visibility->menuVisibleTo($this->user, (string) $menu['key']),
            );
            $features = array_filter(
                $features,
                fn (array $feature): bool => $this->visibility->featureVisibleTo($this->user, (string) $feature['key']),
            );
        }

        if ($this->policyConstraint !== null || $this->modelConstraint !== null) {
            $menus = array_filter(
                $menus,
                fn (array $menu): bool => $this->matchesPolicyAndModel(
                    $this->surfaceIndex->forMenu((string) $menu['key']),
                ),
            );
            $features = array_filter(
                $features,
                fn (array $feature): bool => $this->matchesPolicyAndModel(
                    $this->surfaceIndex->forFeature((string) $feature['key']),
                ),
            );
        }

        return [$menus, $features];
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    /**
     * @param  array<string, mixed>  $row
     */
    private function rowVisibleInArea(array $row): bool
    {
        $area = $row['area'] ?? null;

        if ($this->areaConstraint === null) {
            return true;
        }

        return is_string($area) && $area === $this->areaConstraint;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function filterOrphans(array $rows): array
    {
        if ($this->onlyOrphaned) {
            return array_filter(
                $rows,
                static fn (array $row): bool => (bool) ($row['is_orphaned'] ?? false),
            );
        }

        if ($this->withOrphaned) {
            return $rows;
        }

        return array_filter(
            $rows,
            static fn (array $row): bool => ! (bool) ($row['is_orphaned'] ?? false),
        );
    }

    /**
     * @param  array{policies: list<string>, models: list<string>}  $identifiers
     */
    private function matchesPolicyAndModel(array $identifiers): bool
    {
        $policyOk = true;
        $modelOk = true;

        if ($this->policyConstraint !== null) {
            $policyKey = $this->normalizePolicyKey($this->policyConstraint);
            $policyOk = in_array($policyKey, $identifiers['policies'], true);
        }

        if ($this->modelConstraint !== null) {
            $modelOk = in_array($this->modelConstraint, $identifiers['models'], true);
        }

        return $policyOk && $modelOk;
    }

    private function normalizePolicyKey(string $policyClassOrKey): string
    {
        $resolved = $this->abilities->keyForClass($policyClassOrKey);

        return $resolved ?? $policyClassOrKey;
    }

    private function cacheKey(): string
    {
        $userPart = $this->applyUserVisibility && is_object($this->user) && method_exists($this->user, 'getAuthIdentifier')
            ? (string) $this->user->getAuthIdentifier()
            : 'all';

        return sprintf(
            'laravarc.catalog.manifest.%d.%s.%s',
            $this->state->revision(),
            $userPart,
            md5(json_encode([
                $this->policyConstraint,
                $this->modelConstraint,
                $this->areaConstraint,
                $this->withOrphaned,
                $this->onlyOrphaned,
                $this->applyUserVisibility,
            ], JSON_THROW_ON_ERROR)),
        );
    }
}
