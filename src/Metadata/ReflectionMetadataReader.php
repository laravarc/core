<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Illuminate\Support\Str;
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
     * Returns surface metadata (per controller) plus internal authorization data
     * used by the compiler to build the ability catalog. Only `controllers` is
     * persisted in the metadata artifact.
     *
     * @return array{
     *     controllers: array<string, array{
     *         controller: class-string,
     *         access: 'public'|'authenticated'|'authorized',
     *         menu: array<string, mixed>|null,
     *         features: list<array<string, mixed>>
     *     }>,
     *     authorization: array{
     *         policy: class-string|null,
     *         model: class-string|null,
     *         controllers: array<class-string, array{
     *             policy: class-string|null,
     *             model: class-string|null,
     *             requirements: array<string, list<array{abilities: list<string>, model: class-string|null}>>
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

        $controllers = [];
        $authorizationControllers = [];
        $menus = [];
        $features = [];

        foreach ($this->classDiscoverer->discover($moduleRoot, $moduleNamespace) as $className) {
            if (! str_contains($className, '\\Controllers\\')) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $isPublic = $reflection->getAttributes(PublicAccess::class) !== [];
            $classDefaults = $this->classPolicyRequirements($reflection);

            $menuAttributes = $reflection->getAttributes(Menu::class);

            if (count($menuAttributes) > 1) {
                throw new MetadataCompileException(sprintf(
                    'Controller [%s] declares more than one Menu attribute; at most one is allowed.',
                    $className,
                ));
            }

            $menu = $menuAttributes === []
                ? null
                : $this->normalizeMenu($menuAttributes[0]->newInstance(), $className);

            $controllerFeatures = [];

            foreach ($reflection->getAttributes(Feature::class) as $attribute) {
                /** @var Feature $feature */
                $feature = $attribute->newInstance();
                $normalized = $this->normalizeFeature($feature, $className);

                if (($normalized['key'] ?? null) === ($normalized['menu'] ?? null) && isset($normalized['menu'])) {
                    throw new MetadataCompileException(sprintf(
                        'Feature [%s] on controller [%s] duplicates its own menu key; the menu renders itself.',
                        (string) ($normalized['key'] ?? ''),
                        $className,
                    ));
                }

                if ($menu !== null && isset($normalized['menu']) && $normalized['menu'] === $menu['key']) {
                    throw new MetadataCompileException(sprintf(
                        'Feature [%s] on controller [%s] cannot reference menu key [%s] declared on the same controller; declare the feature on another controller.',
                        (string) ($normalized['key'] ?? ''),
                        $className,
                        (string) $menu['key'],
                    ));
                }

                $controllerFeatures[] = $normalized;
                $features[] = $normalized;
            }

            if ($menu !== null) {
                $menus[] = $menu;
            }

            $controllerDefaults = $this->conventionResolver->resolveController(
                $moduleRoot,
                $moduleNamespace,
                $className,
                $moduleDefaults,
            );

            $policyClass = $controllerDefaults['policy'];
            $requirementsByMethod = [];
            $accessKinds = [];

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

                if ($requirements === []) {
                    $accessKinds[] = $isPublic ? 'public' : 'authenticated';

                    continue;
                }

                $accessKinds[] = 'authorized';
                $requirementsByMethod[$method->getName()] = $requirements;

                if ($policyClass === null) {
                    foreach ($requirements as $requirement) {
                        $resolved = $requirement['model'] === null
                            ? null
                            : $this->conventionResolver->resolvePolicyForModel(
                                $moduleRoot,
                                $moduleNamespace,
                                $requirement['model'],
                            );

                        if ($resolved !== null) {
                            $policyClass = $resolved;
                            break;
                        }
                    }
                }
            }

            if ($isPublic && $requirementsByMethod !== []) {
                throw new MetadataCompileException(sprintf(
                    'Controller [%s] mixes public access with policy requirements; access must be uniform.',
                    $className,
                ));
            }

            $accessKinds = array_values(array_unique($accessKinds));

            if (count($accessKinds) > 1) {
                throw new MetadataCompileException(sprintf(
                    'Controller [%s] mixes access kinds [%s]; split the controller or align every method.',
                    $className,
                    implode(', ', $accessKinds),
                ));
            }

            $access = $accessKinds[0] ?? ($isPublic ? 'public' : 'authenticated');
            $slug = $this->controllerSlug($className);

            if (isset($controllers[$slug])) {
                throw new MetadataCompileException(sprintf(
                    'Duplicate controller slug [%s] in module [%s] (%s and %s).',
                    $slug,
                    $moduleKey,
                    $controllers[$slug]['controller'],
                    $className,
                ));
            }

            $controllers[$slug] = [
                'controller' => $className,
                'access' => $access,
                'menu' => $menu,
                'features' => $controllerFeatures,
            ];

            $authorizationControllers[$className] = [
                'policy' => $policyClass,
                'model' => $controllerDefaults['model'],
                'requirements' => $requirementsByMethod,
            ];
        }

        $this->assertNoDuplicates($moduleKey, $menus, $features);

        $modulePolicy = $moduleDefaults['policy'];

        if ($modulePolicy === null) {
            foreach ($authorizationControllers as $controller) {
                if ($controller['policy'] !== null) {
                    $modulePolicy = $controller['policy'];
                    break;
                }
            }
        }

        ksort($controllers);

        return [
            'controllers' => $controllers,
            'authorization' => [
                'policy' => $modulePolicy,
                'model' => $moduleDefaults['model'],
                'controllers' => $authorizationControllers,
            ],
        ];
    }

    public function controllerSlug(string $controllerClass): string
    {
        $short = class_basename($controllerClass);

        if (str_ends_with($short, 'Controller')) {
            $short = substr($short, 0, -strlen('Controller'));
        }

        return Str::snake($short);
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
            'parent' => $menu->parent,
            'label' => $menu->label,
            'icon' => $menu->icon,
            'order' => $menu->order,
            'path' => $menu->path,
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

        if ($feature->placement === null || $feature->placement === '') {
            throw new MetadataCompileException(sprintf(
                'Feature [%s] on [%s] requires an explicit placement.',
                $feature->key,
                $declaredOn,
            ));
        }

        if ($feature->menu === '') {
            throw new MetadataCompileException(sprintf(
                'Feature [%s] on [%s] must omit menu or provide a non-empty menu key.',
                $feature->key,
                $declaredOn,
            ));
        }

        return array_filter([
            'key' => $feature->key,
            'label' => $feature->label,
            'menu' => $feature->menu,
            'placement' => $feature->placement,
            'order' => $feature->order,
            'path' => $feature->path,
        ], static fn (mixed $value): bool => $value !== null);
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

            if ($seen[$value] ?? false) {
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
