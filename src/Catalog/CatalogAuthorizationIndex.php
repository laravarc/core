<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use DateTimeImmutable;
use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;
use Laravarc\Core\Support\CachePath;

final class CatalogAuthorizationIndex
{
    public const SCHEMA_VERSION = 1;

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(
        private readonly MetadataReader $metadata,
        private readonly AbilityCatalog $abilities,
        private readonly ?string $path = null,
    ) {}

    public function tree(): AuthorizationTree
    {
        $payload = $this->payload();
        $controllers = [];

        foreach ($payload['trees'] as $controller => $methods) {
            if (! is_string($controller) || ! is_array($methods)) {
                continue;
            }

            $controllerTree = [];

            foreach ($methods as $method => $orGroups) {
                if (! is_string($method) || ! is_array($orGroups)) {
                    continue;
                }

                $groups = [];

                foreach ($orGroups as $group) {
                    if (! is_array($group)) {
                        continue;
                    }

                    $refs = [];

                    foreach ($group as $row) {
                        if (is_array($row)) {
                            $refs[] = AbilityRef::fromStored($row);
                        }
                    }

                    $groups[] = $refs;
                }

                $controllerTree[$method] = $groups;
            }

            $controllers[$controller] = $controllerTree;
        }

        return new AuthorizationTree($controllers);
    }

    /**
     * Ability refs for surfaces — first OR-group only per gated method.
     *
     * @param  list<SurfaceRef>  $surfaces
     * @return list<AbilityRef>
     */
    public function abilitiesForSurfaces(array $surfaces): array
    {
        return $this->collectAbilityRefs($surfaces, firstOrGroupOnly: true);
    }

    /**
     * @param  list<SurfaceRef>  $surfaces
     * @return list<string>
     */
    public function policiesForSurfaces(array $surfaces): array
    {
        $policies = [];

        foreach ($surfaces as $surface) {
            $bindings = $this->surfaceBindings($surface->kind, $surface->key);

            foreach ($bindings['policies'] as $policy) {
                $policies[$policy] = true;
            }
        }

        $result = array_keys($policies);
        sort($result);

        return $result;
    }

    /**
     * @param  list<string>  $policyKeys
     * @return array{menus: list<string>, features: list<string>}
     */
    public function surfacesForPolicies(array $policyKeys): array
    {
        $payload = $this->payload();
        $policySurfaces = $payload['policy_surfaces'] ?? [];
        $menus = [];
        $features = [];

        foreach ($policyKeys as $policyKey) {
            if (! is_string($policyKey) || $policyKey === '') {
                continue;
            }

            $entry = $this->policySurfaceEntry($policySurfaces, $policyKey);

            if ($entry === null) {
                continue;
            }

            foreach ($entry['menus'] ?? [] as $menuKey) {
                if (is_string($menuKey) && $menuKey !== '') {
                    $menus[$menuKey] = true;
                }
            }

            foreach ($entry['features'] ?? [] as $featureKey) {
                if (is_string($featureKey) && $featureKey !== '') {
                    $features[$featureKey] = true;
                }
            }
        }

        return [
            'menus' => array_keys($menus),
            'features' => array_keys($features),
        ];
    }

    /**
     * @param  list<AbilityRef>  $abilities
     * @return list<string>
     */
    public function menuKeysForAbilities(array $abilities): array
    {
        $payload = $this->payload();
        $policySurfaces = $payload['policy_surfaces'] ?? [];
        $menus = [];

        foreach ($abilities as $ref) {
            if (! $ref instanceof AbilityRef) {
                continue;
            }

            $entry = $this->policySurfaceEntry($policySurfaces, $ref->policyKey);

            if ($entry === null) {
                continue;
            }

            foreach ($entry['menus'] ?? [] as $menuKey) {
                if (is_string($menuKey) && $menuKey !== '') {
                    $menus[$menuKey] = true;
                }
            }
        }

        return array_keys($menus);
    }

    /**
     * @param  array<string, array<string, mixed>>  $policySurfaces
     */
    private function policySurfaceEntry(array $policySurfaces, string $policyKey): ?array
    {
        $entry = $policySurfaces[$policyKey] ?? null;

        if (is_array($entry)) {
            return $entry;
        }

        foreach (self::policyKeyAliases($policyKey) as $variant) {
            if ($variant === $policyKey) {
                continue;
            }

            $entry = $policySurfaces[$variant] ?? null;

            if (is_array($entry)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Index and authorizer policy key forms (with/without `_policy` suffix).
     *
     * @return list<string>
     */
    public static function policyKeyAliases(string $policyKey): array
    {
        $variants = [$policyKey];

        if (str_ends_with($policyKey, '_policy')) {
            $withoutSuffix = substr($policyKey, 0, -strlen('_policy'));
            if ($withoutSuffix !== '') {
                $variants[] = $withoutSuffix;
            }
        } else {
            $variants[] = $policyKey.'_policy';
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param  list<string>  $menuKeys
     * @param  list<string>  $featureKeys
     * @return array{by_menu: array<string, list<AbilityRef>>, unassigned: list<AbilityRef>}
     */
    public function abilitiesByMenu(array $menuKeys, array $featureKeys): array
    {
        $byMenu = [];
        $unassigned = [];

        foreach ($menuKeys as $menuKey) {
            if (! is_string($menuKey) || $menuKey === '') {
                continue;
            }

            $refs = $this->abilitiesForSurfaces([SurfaceRef::menu($menuKey)]);

            if ($refs !== []) {
                $byMenu[$menuKey] = $refs;
            }
        }

        foreach ($featureKeys as $featureKey) {
            if (! is_string($featureKey) || $featureKey === '') {
                continue;
            }

            $refs = $this->abilitiesForSurfaces([SurfaceRef::feature($featureKey)]);

            if ($refs === []) {
                continue;
            }

            $unassigned = [...$unassigned, ...$refs];
        }

        return [
            'by_menu' => $byMenu,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * @param  list<SurfaceRef>  $surfaces
     * @return list<AbilityRef>
     */
    private function collectAbilityRefs(array $surfaces, bool $firstOrGroupOnly): array
    {
        $payload = $this->payload();
        $surfaceIndex = $this->surfaceLookup($payload);
        $result = [];
        $seen = [];

        foreach ($surfaces as $surface) {
            $lookupKey = $surface->kind.':'.$surface->key;
            $entry = $surfaceIndex[$lookupKey] ?? null;

            if ($entry === null) {
                continue;
            }

            foreach ($entry['controllers'] as $controller) {
                $methods = $payload['trees'][$controller] ?? [];

                if (! is_array($methods)) {
                    continue;
                }

                foreach ($methods as $orGroups) {
                    if (! is_array($orGroups) || $orGroups === []) {
                        continue;
                    }

                    $groupsToWalk = $firstOrGroupOnly ? [($orGroups[0] ?? [])] : $orGroups;

                    foreach ($groupsToWalk as $group) {
                        if (! is_array($group)) {
                            continue;
                        }

                        foreach ($group as $row) {
                            if (! is_array($row)) {
                                continue;
                            }

                            $ref = AbilityRef::fromStored($row);
                            $abilityKey = $ref->toAbilityKey();

                            if (isset($seen[$abilityKey])) {
                                continue;
                            }

                            $seen[$abilityKey] = true;
                            $result[] = $ref;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @return array{policies: list<string>, models: list<string>}
     */
    public function bindingsForMenu(string $menuKey): array
    {
        return $this->surfaceBindings(SurfaceRef::KIND_MENU, $menuKey);
    }

    /**
     * @return array{policies: list<string>, models: list<string>}
     */
    public function bindingsForFeature(string $featureKey): array
    {
        return $this->surfaceBindings(SurfaceRef::KIND_FEATURE, $featureKey);
    }

    public function write(?string $path = null): void
    {
        $destination = $path ?? $this->resolvedPath();
        $payload = $this->compilePayload();
        $this->writePayload($destination, $payload);
        $this->data = $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function compilePayload(): array
    {
        $menuControllers = [];
        $featureControllers = [];
        $menuKeys = [];
        $featureKeys = [];

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

                $menu = is_array($controller['menu'] ?? null) ? $controller['menu'] : null;

                if ($menu !== null && isset($menu['key'])) {
                    $menuKey = (string) $menu['key'];

                    if (isset($featureKeys[$menuKey])) {
                        throw new MetadataCompileException(sprintf(
                            'Catalog authorization index: menu key [%s] conflicts with an existing feature key.',
                            $menuKey,
                        ));
                    }

                    $menuKeys[$menuKey] = true;
                    $menuControllers[$menuKey] ??= [];
                    $menuControllers[$menuKey][$controllerClass] = true;
                }

                foreach ($controller['features'] ?? [] as $feature) {
                    if (! is_array($feature) || empty($feature['key'])) {
                        continue;
                    }

                    $featureKey = (string) $feature['key'];

                    if (isset($featureKeys[$featureKey])) {
                        throw new MetadataCompileException(sprintf(
                            'Catalog authorization index: duplicate feature key [%s].',
                            $featureKey,
                        ));
                    }

                    if (isset($menuKeys[$featureKey])) {
                        throw new MetadataCompileException(sprintf(
                            'Catalog authorization index: feature key [%s] conflicts with menu key of the same name.',
                            $featureKey,
                        ));
                    }

                    $featureKeys[$featureKey] = true;
                    $featureControllers[$featureKey] = $controllerClass;
                }
            }
        }

        $surfaces = [];

        foreach ($menuControllers as $menuKey => $controllers) {
            $surfaces[] = [
                'kind' => SurfaceRef::KIND_MENU,
                'key' => $menuKey,
                'controllers' => array_keys($controllers),
            ];
        }

        foreach ($featureControllers as $featureKey => $controllerClass) {
            $surfaces[] = [
                'kind' => SurfaceRef::KIND_FEATURE,
                'key' => $featureKey,
                'controllers' => [$controllerClass],
            ];
        }

        $controllerClasses = [];

        foreach ($surfaces as $surface) {
            foreach ($surface['controllers'] as $controllerClass) {
                $controllerClasses[$controllerClass] = true;
            }
        }

        $trees = [];
        $policySurfaces = [];

        foreach (array_keys($controllerClasses) as $controllerClass) {
            $methodGroups = $this->abilities->gates()[$controllerClass] ?? [];

            if (! is_array($methodGroups)) {
                continue;
            }

            $storedMethods = [];

            foreach ($methodGroups as $method => $orGroups) {
                if (! is_string($method) || ! is_array($orGroups)) {
                    continue;
                }

                $storedOrGroups = [];

                foreach ($orGroups as $group) {
                    if (! is_array($group)) {
                        continue;
                    }

                    $storedGroup = [];

                    foreach ($group as $abilityKey) {
                        if (! is_string($abilityKey) || $abilityKey === '') {
                            continue;
                        }

                        $storedGroup[] = AbilityRef::fromAbilityKey($abilityKey)->toArray();
                    }

                    if ($storedGroup !== []) {
                        $storedOrGroups[] = $storedGroup;
                    }
                }

                if ($storedOrGroups !== []) {
                    $storedMethods[$method] = $storedOrGroups;
                }
            }

            if ($storedMethods !== []) {
                $trees[$controllerClass] = $storedMethods;
            }
        }

        foreach ($surfaces as $surface) {
            foreach ($surface['controllers'] as $controllerClass) {
                foreach ($this->policyKeysInTree($trees[$controllerClass] ?? []) as $policyKey) {
                    $bucket = $surface['kind'] === SurfaceRef::KIND_MENU ? 'menus' : 'features';
                    $policySurfaces[$policyKey][$bucket][$surface['key']] = true;
                }
            }
        }

        $normalizedPolicySurfaces = [];

        foreach ($policySurfaces as $policyKey => $groups) {
            $normalizedPolicySurfaces[$policyKey] = [
                'menus' => array_keys($groups['menus'] ?? []),
                'features' => array_keys($groups['features'] ?? []),
            ];
        }

        ksort($normalizedPolicySurfaces);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'compiled_at' => (new DateTimeImmutable)->format(DATE_ATOM),
            'surfaces' => $surfaces,
            'trees' => $trees,
            'policy_surfaces' => $normalizedPolicySurfaces,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path = $this->resolvedPath();

        if (is_file($path)) {
            $this->data = $this->readValidatedFile($path);

            return $this->data;
        }

        if ($this->allowsInRequestCompile()) {
            try {
                $this->data = $this->compilePayload();
            } catch (MetadataCompileException $exception) {
                throw $exception;
            }

            return $this->data;
        }

        throw new MetadataCompileException(sprintf(
            'Catalog authorization index is missing or unreadable at [%s]. Run laravarc:cache build.',
            $path,
        ));
    }

    private function resolvedPath(): string
    {
        return $this->path ?? CachePath::authorization();
    }

    private function allowsInRequestCompile(): bool
    {
        return app()->environment('local', 'testing');
    }

    /**
     * @return array<string, mixed>
     */
    private function readValidatedFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new MetadataCompileException(sprintf(
                'Catalog authorization index is missing or unreadable at [%s].',
                $path,
            ));
        }

        $data = require $path;

        return $this->validatePayload($data, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(mixed $data, string $path): array
    {
        if (! is_array($data)) {
            throw new MetadataCompileException(sprintf(
                'Catalog authorization index at [%s] must return an array.',
                $path,
            ));
        }

        if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new MetadataCompileException(sprintf(
                'Catalog authorization index at [%s] has unsupported schema_version.',
                $path,
            ));
        }

        foreach (['surfaces', 'trees', 'policy_surfaces'] as $required) {
            if (! isset($data[$required]) || ! is_array($data[$required])) {
                throw new MetadataCompileException(sprintf(
                    'Catalog authorization index at [%s] is missing required key [%s].',
                    $path,
                    $required,
                ));
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writePayload(string $path, array $payload): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new MetadataCompileException(sprintf('Unable to create catalog authorization directory [%s].', $directory));
        }

        $temporary = $path.'.'.uniqid('arc-auth-', true).'.tmp';
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n";

        if (file_put_contents($temporary, $contents) === false) {
            throw new MetadataCompileException(sprintf('Unable to write catalog authorization index [%s].', $temporary));
        }

        if (@rename($temporary, $path) === false) {
            @unlink($temporary);

            throw new MetadataCompileException(sprintf('Unable to publish catalog authorization index [%s].', $path));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{kind: string, key: string, controllers: list<string>}>
     */
    private function surfaceLookup(array $payload): array
    {
        $lookup = [];

        foreach ($payload['surfaces'] as $surface) {
            if (! is_array($surface)) {
                continue;
            }

            $kind = $surface['kind'] ?? null;
            $key = $surface['key'] ?? null;
            $controllers = $surface['controllers'] ?? [];

            if (! is_string($kind) || ! is_string($key) || ! is_array($controllers)) {
                continue;
            }

            $lookup[$kind.':'.$key] = [
                'kind' => $kind,
                'key' => $key,
                'controllers' => array_values(array_filter(
                    $controllers,
                    static fn (mixed $controller): bool => is_string($controller) && $controller !== '',
                )),
            ];
        }

        return $lookup;
    }

    /**
     * @return array{policies: list<string>, models: list<string>}
     */
    private function surfaceBindings(string $kind, string $key): array
    {
        $payload = $this->payload();
        $lookupKey = $kind.':'.$key;
        $entry = $this->surfaceLookup($payload)[$lookupKey] ?? null;

        if ($entry === null) {
            return ['policies' => [], 'models' => []];
        }

        $policies = [];
        $models = [];

        foreach ($entry['controllers'] as $controller) {
            foreach ($this->policyKeysInTree($payload['trees'][$controller] ?? []) as $policyKey) {
                $policies[$policyKey] = true;

                foreach ($this->modelsForPolicy($policyKey) as $modelClass) {
                    $models[$modelClass] = true;
                }
            }
        }

        return [
            'policies' => array_keys($policies),
            'models' => array_keys($models),
        ];
    }

    /**
     * @param  array<string, list<list<array{policy_key: string, ability: string}>>>  $methodTree
     * @return list<string>
     */
    private function policyKeysInTree(array $methodTree): array
    {
        $policies = [];

        foreach ($methodTree as $orGroups) {
            if (! is_array($orGroups)) {
                continue;
            }

            foreach ($orGroups as $group) {
                if (! is_array($group)) {
                    continue;
                }

                foreach ($group as $row) {
                    if (is_array($row) && isset($row['policy_key'])) {
                        $policies[(string) $row['policy_key']] = true;
                    }
                }
            }
        }

        return array_keys($policies);
    }

    /**
     * @return list<string>
     */
    private function modelsForPolicy(string $policyKey): array
    {
        $policy = $this->abilities->policies()[$policyKey] ?? null;

        if (! is_array($policy)) {
            return [];
        }

        $models = [];

        foreach ($policy['models'] ?? [] as $model) {
            if (is_array($model) && isset($model['class']) && is_string($model['class'])) {
                $models[] = $model['class'];
            }
        }

        return $models;
    }
}
