<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Laravarc\Core\Authorization\PolicyConventionResolver;
use Laravarc\Core\Metadata\Attributes\Feature;
use Laravarc\Core\Metadata\Attributes\Menu;
use Laravarc\Core\Metadata\Attributes\Policy;
use Laravarc\Core\Metadata\Attributes\PublicAccess;
use Laravarc\Core\Metadata\Exceptions\DuplicateMetadataException;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;

final class ReflectionMetadataReader
{
    public function __construct(
        private readonly ModuleClassDiscoverer $classDiscoverer,
        private readonly PolicyConventionResolver $conventionResolver = new PolicyConventionResolver,
    ) {}

    /**
     * @return array{
     *     menus: list<array<string, mixed>>,
     *     features: list<array<string, mixed>>,
     *     policy: array{
     *         model: string|null,
     *         policy: string|null,
     *         abilities: list<string>,
     *         ability_overrides: array<string, array{model?: string, policy?: string}>,
     *         controllers: array<string, array{
     *             model: string|null,
     *             policy: string|null,
     *             public: bool,
     *             methods: array<string, array{requirements: list<array{abilities: list<string>, model: string|null}>}>
     *         }>
     *     }
     * }
     */
    public function readModule(
        string $moduleRoot,
        string $moduleNamespace,
        string $moduleKey,
        string $moduleEntityName,
    ): array {
        $moduleDefaults = $this->conventionResolver->resolveModuleDefaults(
            $moduleRoot,
            $moduleNamespace,
            $moduleEntityName,
        );

        $menus = [];
        $features = [];
        $controllers = [];
        $abilityOverrides = [];
        $abilities = [];

        foreach ($this->classDiscoverer->discover($moduleRoot, $moduleNamespace) as $className) {
            if (! str_contains($className, '\\Controllers\\')) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $isPublic = $reflection->getAttributes(PublicAccess::class) !== [];
            $classDefaults = $this->classPolicyRequirements($reflection);

            foreach ($reflection->getAttributes(Menu::class) as $attribute) {
                /** @var Menu $menu */
                $menu = $attribute->newInstance();
                $menus[] = $this->normalizeMenu($menu, $className);
            }

            foreach ($reflection->getAttributes(Feature::class) as $attribute) {
                /** @var Feature $feature */
                $feature = $attribute->newInstance();
                $normalized = $this->normalizeFeature($feature, $className);
                $normalized['_controller'] = $className;
                $features[] = $normalized;
            }

            $controllerDefaults = $this->conventionResolver->resolveController(
                $moduleRoot,
                $moduleNamespace,
                $className,
                $moduleDefaults,
            );

            $controllerMethods = [];

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }

                if ($method->getName() === '__construct') {
                    continue;
                }

                $requirements = $this->methodPolicyRequirements(
                    method: $method,
                    declaredOn: $className,
                    classDefaults: $classDefaults,
                    controllerDefaults: $controllerDefaults,
                    moduleDefaults: $moduleDefaults,
                );

                if ($requirements !== []) {
                    $controllerMethods[$method->getName()] = ['requirements' => $requirements];

                    foreach ($requirements as $requirement) {
                        foreach ($requirement['abilities'] as $ability) {
                            if (! in_array($ability, $abilities, true)) {
                                $abilities[] = $ability;
                            }

                            if ($requirement['model'] !== null) {
                                $abilityOverrides[$ability] = array_filter([
                                    'model' => $requirement['model'],
                                ]);
                            }
                        }
                    }
                }
            }

            $controllers[$className] = [
                'model' => $controllerDefaults['model'],
                'policy' => $controllerDefaults['policy'],
                'public' => $isPublic,
                'methods' => $controllerMethods,
            ];
        }

        $this->mergePolicyAbilities($moduleRoot, $moduleNamespace, $moduleEntityName, $moduleDefaults, $abilities);

        $features = $this->applyFeatureVisibilityAbilities($features, $controllers);
        $this->assertNoDuplicates($moduleKey, $menus, $features);

        [$menus, $globalFeatures] = $this->nestFeatures($menus, $features);

        sort($abilities);

        return [
            'menus' => $menus,
            'features' => $globalFeatures,
            'policy' => [
                'model' => $moduleDefaults['model'],
                'policy' => $moduleDefaults['policy'],
                'abilities' => $abilities,
                'ability_overrides' => $abilityOverrides,
                'controllers' => $controllers,
            ],
        ];
    }

    /**
     * @param  array{model: string|null, policy: string|null}  $moduleDefaults
     * @param  list<string>  $abilities
     */
    private function mergePolicyAbilities(
        string $moduleRoot,
        string $moduleNamespace,
        string $moduleEntityName,
        array $moduleDefaults,
        array &$abilities,
    ): void {
        $policyClass = $moduleDefaults['policy'];

        if ($policyClass === null) {
            return;
        }

        if (! class_exists($policyClass, false) && is_file($moduleRoot.'/Policies/'.$moduleEntityName.'Policy.php')) {
            require_once $moduleRoot.'/Policies/'.$moduleEntityName.'Policy.php';
        }

        if (! class_exists($policyClass)) {
            return;
        }

        foreach ((new \ReflectionClass($policyClass))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $policyClass || $method->getName() === '__construct') {
                continue;
            }

            if (! in_array($method->getName(), $abilities, true)) {
                $abilities[] = $method->getName();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @param  array<string, array{methods: array<string, array{requirements: list<array{abilities: list<string>, model: string|null}>}>}>  $controllers
     * @return list<array<string, mixed>>
     */
    private function applyFeatureVisibilityAbilities(array $features, array $controllers): array
    {
        return array_map(function (array $feature) use ($controllers): array {
            $controllerClass = $feature['_controller'] ?? null;
            unset($feature['_controller']);

            if (! isset($feature['visibility_ability'])) {
                $feature['visibility_ability'] = is_string($controllerClass)
                    ? $this->resolveFeatureVisibilityAbilityForController($controllers[$controllerClass] ?? [])
                    : $this->resolveFeatureVisibilityAbility($controllers);
            }

            return $feature;
        }, $features);
    }

    /**
     * @param  array{methods?: array<string, array{requirements: list<array{abilities: list<string>, model: string|null}>}>}  $controller
     */
    private function resolveFeatureVisibilityAbilityForController(array $controller): ?string
    {
        foreach ($controller['methods'] ?? [] as $method) {
            foreach ($method['requirements'] ?? [] as $requirement) {
                if (in_array('viewAny', $requirement['abilities'] ?? [], true)) {
                    return 'viewAny';
                }
            }
        }

        return 'viewAny';
    }

    /**
     * @param  array<string, array{methods: array<string, array{requirements: list<array{abilities: list<string>, model: string|null}>}>}>  $controllers
     */
    private function resolveFeatureVisibilityAbility(array $controllers): ?string
    {
        foreach ($controllers as $controller) {
            foreach ($controller['methods'] ?? [] as $method) {
                foreach ($method['requirements'] ?? [] as $requirement) {
                    if (in_array('viewAny', $requirement['abilities'] ?? [], true)) {
                        return 'viewAny';
                    }
                }
            }
        }

        return 'viewAny';
    }

    /**
     * @return list<array{abilities: list<string>, model: string|null}>
     */
    private function classPolicyRequirements(\ReflectionClass $reflection): array
    {
        $requirements = [];

        foreach ($reflection->getAttributes(Policy::class) as $attribute) {
            /** @var Policy $policy */
            $policy = $attribute->newInstance();
            $requirements[] = [
                'abilities' => $this->normalizeAbilities($policy->ability, $reflection->getName()),
                'model' => $policy->model,
            ];
        }

        return $requirements;
    }

    /**
     * @param  list<array{abilities: list<string>, model: string|null}>  $classDefaults
     * @param  array{model: string|null, policy: string|null}  $controllerDefaults
     * @param  array{model: string|null, policy: string|null}  $moduleDefaults
     * @return list<array{abilities: list<string>, model: string|null}>
     */
    private function methodPolicyRequirements(
        \ReflectionMethod $method,
        string $declaredOn,
        array $classDefaults,
        array $controllerDefaults,
        array $moduleDefaults,
    ): array {
        $requirements = [];

        foreach ($method->getAttributes(Policy::class) as $attribute) {
            /** @var Policy $policy */
            $policy = $attribute->newInstance();
            $requirements[] = [
                'abilities' => $this->normalizeAbilities($policy->ability, $declaredOn.'::'.$method->getName()),
                'model' => $this->resolveRequirementModel(
                    explicitModel: $policy->model,
                    controllerDefaults: $controllerDefaults,
                    moduleDefaults: $moduleDefaults,
                    declaredOn: $declaredOn.'::'.$method->getName(),
                ),
            ];
        }

        if ($requirements === [] && $classDefaults !== []) {
            return array_map(
                fn (array $requirement): array => [
                    'abilities' => $requirement['abilities'],
                    'model' => $this->resolveRequirementModel(
                        explicitModel: $requirement['model'],
                        controllerDefaults: $controllerDefaults,
                        moduleDefaults: $moduleDefaults,
                        declaredOn: $declaredOn.'::'.$method->getName(),
                    ),
                ],
                $classDefaults,
            );
        }

        return $requirements;
    }

    /**
     * @param  array{model: string|null, policy: string|null}  $controllerDefaults
     * @param  array{model: string|null, policy: string|null}  $moduleDefaults
     */
    private function resolveRequirementModel(
        ?string $explicitModel,
        array $controllerDefaults,
        array $moduleDefaults,
        string $declaredOn,
    ): ?string {
        if ($explicitModel !== null) {
            if (! class_exists($explicitModel)) {
                throw new MetadataCompileException(sprintf(
                    'Policy metadata on [%s] references missing model class [%s].',
                    $declaredOn,
                    $explicitModel,
                ));
            }

            return $explicitModel;
        }

        $resolved = $controllerDefaults['model'] ?? $moduleDefaults['model'];

        if ($resolved === null) {
            throw new MetadataCompileException(sprintf(
                'Policy metadata on [%s] could not resolve a target model. Provide an explicit model parameter or follow module/controller naming conventions.',
                $declaredOn,
            ));
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private function normalizeAbilities(string|array $ability, string $declaredOn): array
    {
        $abilities = is_array($ability) ? $ability : [$ability];
        $normalized = [];

        foreach ($abilities as $item) {
            if (! is_string($item) || $item === '') {
                throw new MetadataCompileException(sprintf(
                    'Policy metadata on [%s] requires non-empty ability names.',
                    $declaredOn,
                ));
            }

            $normalized[] = $item;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMenu(Menu $menu, string $declaredOn): array
    {
        if ($menu->key === '' || $menu->label === '') {
            throw new MetadataCompileException(sprintf(
                'Menu metadata on [%s] requires non-empty key and label.',
                $declaredOn,
            ));
        }

        return array_filter([
            'key' => $menu->key,
            'label' => $menu->label,
            'icon' => $menu->icon,
            'group' => $menu->group,
            'order' => $menu->order,
            'parent' => $menu->parent,
            'visible' => $menu->visible,
            'features' => [],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFeature(Feature $feature, string $declaredOn): array
    {
        if ($feature->key === '' || $feature->label === '') {
            throw new MetadataCompileException(sprintf(
                'Feature metadata on [%s] requires non-empty key and label.',
                $declaredOn,
            ));
        }

        return array_filter([
            'key' => $feature->key,
            'label' => $feature->label,
            'menu' => $feature->menu,
            'placement' => $feature->placement,
            'order' => $feature->order,
            'description' => $feature->description,
            'visibility_ability' => ($feature->visibilityAbility !== null && $feature->visibilityAbility !== '')
                ? $feature->visibilityAbility
                : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  list<array<string, mixed>>  $menus
     * @param  list<array<string, mixed>>  $features
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function nestFeatures(array $menus, array $features): array
    {
        $menusByKey = [];

        foreach ($menus as $menu) {
            $menu['features'] = [];
            $menusByKey[$menu['key']] = $menu;
        }

        $globalFeatures = [];

        foreach ($features as $feature) {
            $menuKey = $feature['menu'] ?? null;
            $featureEntry = $feature;
            unset($featureEntry['menu']);

            if (is_string($menuKey) && $menuKey !== '') {
                if (! isset($menusByKey[$menuKey])) {
                    throw new MetadataCompileException(sprintf(
                        'Feature [%s] references unknown menu key [%s].',
                        $feature['key'],
                        $menuKey,
                    ));
                }

                $menusByKey[$menuKey]['features'][] = $featureEntry;

                continue;
            }

            $globalFeatures[] = $featureEntry;
        }

        $nestedMenus = array_values($menusByKey);

        foreach ($nestedMenus as &$menu) {
            usort($menu['features'], static fn (array $left, array $right): int => ($left['order'] ?? 0) <=> ($right['order'] ?? 0));
        }
        unset($menu);

        usort($globalFeatures, static fn (array $left, array $right): int => ($left['order'] ?? 0) <=> ($right['order'] ?? 0));

        return [$nestedMenus, $globalFeatures];
    }

    /**
     * @param  list<array<string, mixed>>  $menus
     * @param  list<array<string, mixed>>  $features
     */
    private function assertNoDuplicates(string $moduleKey, array $menus, array $features): void
    {
        $this->assertUniqueKeys($moduleKey, 'menu', $menus, 'key');

        $featureKeys = [];

        foreach ($features as $feature) {
            $this->assertFeatureKey($moduleKey, $feature, $featureKeys);
        }
    }

    /**
     * @param  array<string, true>  $featureKeys
     * @param  array<string, mixed>  $feature
     */
    private function assertFeatureKey(string $moduleKey, array $feature, array &$featureKeys): void
    {
        $key = (string) ($feature['key'] ?? '');

        if (isset($featureKeys[$key])) {
            throw new DuplicateMetadataException(sprintf(
                'Duplicate feature key [%s] in module [%s].',
                $key,
                $moduleKey,
            ));
        }

        $featureKeys[$key] = true;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function assertUniqueKeys(string $moduleKey, string $type, array $entries, string $field): void
    {
        $seen = [];

        foreach ($entries as $entry) {
            $value = (string) ($entry[$field] ?? '');

            if (isset($seen[$value])) {
                throw new DuplicateMetadataException(sprintf(
                    'Duplicate %s key [%s] in module [%s].',
                    $type,
                    $value,
                    $moduleKey,
                ));
            }

            $seen[$value] = true;
        }
    }
}
