<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Laravarc\Core\Authorization\AbilityCatalogWriter;
use Laravarc\Core\Support\KeyIdentifier;
use Laravarc\Core\Contracts\MetadataArtifactStore;
use Laravarc\Core\Contracts\MetadataCompiler;
use Laravarc\Core\Discovery\ModuleManifestEntry;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;
use Laravarc\Core\Metadata\Support\PathValidator;

final class CoreMetadataCompiler implements MetadataCompiler
{
    private readonly PathValidator $pathValidator;

    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ReflectionMetadataReader $reader,
        private readonly ServiceMetadataReader $serviceReader,
        private readonly ListenerMetadataReader $listenerReader,
        private readonly MetadataArtifactStore $store,
        private readonly ?ExtensionManager $extensions = null,
        ?PathValidator $pathValidator = null,
        private readonly ?AbilityCatalogWriter $abilityWriter = null,
    ) {
        $this->pathValidator = $pathValidator ?? new PathValidator;
    }

    public function compile(bool $dryRun = false, ?string $modulePath = null): MetadataCompileResult
    {
        $this->extensions?->dispatch(ExtensionHook::MetadataCompileBefore, [
            'dryRun' => $dryRun,
            'modulePath' => $modulePath,
        ]);

        // Manifest carries services/listeners; rebuild it after contract sync so
        // bindings reflect the current contract files (see ModuleRegistry::refresh).
        if (! $dryRun) {
            $this->moduleRegistry->refresh();
        }

        $rawModules = $this->compileModules($modulePath);
        ksort($rawModules);

        $this->assertNoDuplicateMenuKeys($rawModules);
        $this->assertNoDuplicateFeatureKeysAcrossModules($rawModules);
        $this->assertFeatureKeysDisjointFromMenuKeys($rawModules);
        $this->assertFeatureMenusDoNotReferenceFeatureKeys($rawModules);
        $this->assertFeaturesReferenceKnownMenus($rawModules);
        $this->validateDeclaredPaths($rawModules);

        $warningModules = $modulePath === null
            ? array_keys($rawModules)
            : [$this->moduleRegistry->requireByPath($modulePath)->key];

        $warnings = $this->collectPathFallbackWarnings($rawModules, $warningModules);

        // The ability catalog is global: a partial module compile must still see
        // every module's declarations so previously compiled policies survive.
        $catalogModules = $modulePath === null ? $rawModules : $this->compileModules(null);
        [$policies, $gates, $policyWarnings] = $this->buildAbilityCatalog($catalogModules);
        $warnings = [...$warnings, ...$policyWarnings];

        $modules = [];

        foreach ($rawModules as $moduleKey => $moduleData) {
            $modules[$moduleKey] = ['controllers' => $moduleData['controllers']];
        }

        $compiledAt = (new DateTimeImmutable)->format(DATE_ATOM);

        $artifact = new MetadataArtifact(
            modules: $modules,
            compiledAt: $compiledAt,
            fingerprint: 'sha256:'.hash('sha256', json_encode($modules, JSON_THROW_ON_ERROR)),
        );

        $menuCount = 0;
        $featureCount = 0;

        foreach ($modules as $moduleData) {
            foreach ($moduleData['controllers'] as $controller) {
                if (($controller['menu'] ?? null) !== null) {
                    $menuCount++;
                }

                $featureCount += count($controller['features'] ?? []);
            }
        }

        $abilityCount = 0;

        foreach ($policies as $policy) {
            foreach ($policy['models'] as $model) {
                $abilityCount += count($model['abilities']);
            }
        }

        $persisted = false;

        if (! $dryRun && $this->store->isPersistent()) {
            $this->store->write($artifact);
            $this->abilityWriter?->write($policies, $gates);
            $persisted = true;
        }

        $result = new MetadataCompileResult(
            artifact: $artifact,
            moduleCount: count($modules),
            moduleKeys: array_keys($modules),
            menuCount: $menuCount,
            featureCount: $featureCount,
            policyCount: $abilityCount,
            persisted: $persisted,
            warnings: $warnings,
        );

        $this->extensions?->dispatch(ExtensionHook::MetadataCompileAfter, $result);

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $modules
     */
    private function assertNoDuplicateMenuKeys(array $modules): void
    {
        $seen = [];

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                $menu = $controller['menu'] ?? null;

                if (! is_array($menu)) {
                    continue;
                }

                $menuKey = (string) ($menu['key'] ?? '');

                if (isset($seen[$menuKey])) {
                    throw new MetadataCompileException(sprintf(
                        'Duplicate menu key [%s] declared in modules [%s] and [%s].',
                        $menuKey,
                        $seen[$menuKey],
                        $moduleKey,
                    ));
                }

                $seen[$menuKey] = $moduleKey;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $modules
     */
    private function assertNoDuplicateFeatureKeysAcrossModules(array $modules): void
    {
        $seen = [];

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                foreach ($controller['features'] ?? [] as $feature) {
                    $featureKey = (string) ($feature['key'] ?? '');

                    if ($featureKey === '') {
                        continue;
                    }

                    if (isset($seen[$featureKey])) {
                        throw new MetadataCompileException(sprintf(
                            'Duplicate feature key [%s] declared in modules [%s] and [%s].',
                            $featureKey,
                            $seen[$featureKey],
                            $moduleKey,
                        ));
                    }

                    $seen[$featureKey] = $moduleKey;
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $modules
     */
    private function assertFeatureKeysDisjointFromMenuKeys(array $modules): void
    {
        $menuKeys = [];

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                $menu = $controller['menu'] ?? null;

                if (is_array($menu) && isset($menu['key'])) {
                    $menuKeys[(string) $menu['key']] = $moduleKey;
                }
            }
        }

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                foreach ($controller['features'] ?? [] as $feature) {
                    $featureKey = (string) ($feature['key'] ?? '');

                    if ($featureKey === '' || ! isset($menuKeys[$featureKey])) {
                        continue;
                    }

                    throw new MetadataCompileException(sprintf(
                        'Feature key [%s] in module [%s] conflicts with menu key [%s] in module [%s].',
                        $featureKey,
                        $moduleKey,
                        $featureKey,
                        $menuKeys[$featureKey],
                    ));
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $modules
     */
    private function assertFeatureMenusDoNotReferenceFeatureKeys(array $modules): void
    {
        $featureKeys = [];

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                foreach ($controller['features'] ?? [] as $feature) {
                    $featureKey = (string) ($feature['key'] ?? '');

                    if ($featureKey !== '') {
                        $featureKeys[$featureKey] = $moduleKey;
                    }
                }
            }
        }

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                foreach ($controller['features'] ?? [] as $feature) {
                    $menuKey = $feature['menu'] ?? null;

                    if (! is_string($menuKey) || $menuKey === '') {
                        continue;
                    }

                    if (isset($featureKeys[$menuKey])) {
                        throw new MetadataCompileException(sprintf(
                            'Feature [%s] in module [%s] references menu key [%s] that clashes with feature key [%s] in module [%s].',
                            (string) ($feature['key'] ?? ''),
                            $moduleKey,
                            $menuKey,
                            $menuKey,
                            $featureKeys[$menuKey],
                        ));
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $modules
     */
    private function assertFeaturesReferenceKnownMenus(array $modules): void
    {
        $knownMenuKeys = [];

        foreach ($modules as $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                $menu = $controller['menu'] ?? null;

                if (is_array($menu) && isset($menu['key'])) {
                    $knownMenuKeys[(string) $menu['key']] = true;
                }
            }
        }

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                foreach ($controller['features'] ?? [] as $feature) {
                    $menuKey = $feature['menu'] ?? null;

                    if (! is_string($menuKey) || $menuKey === '') {
                        continue;
                    }

                    if (! isset($knownMenuKeys[$menuKey])) {
                        throw new MetadataCompileException(sprintf(
                            'Feature [%s] in module [%s] references unknown menu key [%s].',
                            $feature['key'] ?? '',
                            $moduleKey,
                            $menuKey,
                        ));
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $modules
     */
    private function validateDeclaredPaths(array $modules): void
    {
        $menuPaths = [];

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                $menu = $controller['menu'] ?? null;

                if (! is_array($menu) || ! isset($menu['path'])) {
                    continue;
                }

                $menuKey = (string) ($menu['key'] ?? '');

                try {
                    $menuPaths[$menuKey] = $this->pathValidator->validateMenuPath((string) $menu['path'], 'compile');
                } catch (\InvalidArgumentException $exception) {
                    throw new MetadataCompileException(sprintf(
                        'Menu [%s] in module [%s] has an invalid path: %s',
                        $menuKey,
                        $moduleKey,
                        $exception->getMessage(),
                    ), previous: $exception);
                }
            }
        }

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['controllers'] ?? [] as $controller) {
                $menu = $controller['menu'] ?? null;
                $menuKey = is_array($menu) && isset($menu['key']) ? (string) $menu['key'] : null;

                foreach ($controller['features'] ?? [] as $feature) {
                    if (! isset($feature['path'])) {
                        continue;
                    }

                    $featureKey = (string) ($feature['key'] ?? '');
                    $featureMenu = $feature['menu'] ?? $menuKey;
                    $menuPath = is_string($featureMenu) ? ($menuPaths[$featureMenu] ?? null) : null;

                    try {
                        $this->pathValidator->validateFeatureSuffix((string) $feature['path'], $menuPath, 'compile');
                    } catch (\InvalidArgumentException $exception) {
                        throw new MetadataCompileException(sprintf(
                            'Feature [%s] in module [%s] has an invalid path: %s',
                            $featureKey,
                            $moduleKey,
                            $exception->getMessage(),
                        ), previous: $exception);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $modules
     * @param  list<string>  $moduleKeys
     * @return list<string>
     */
    private function collectPathFallbackWarnings(array $modules, array $moduleKeys): array
    {
        $warnings = [];

        foreach ($moduleKeys as $moduleKey) {
            $moduleData = $modules[$moduleKey] ?? null;

            if (! is_array($moduleData)) {
                continue;
            }

            foreach ($moduleData['controllers'] ?? [] as $controller) {
                $menu = $controller['menu'] ?? null;

                if (is_array($menu) && (string) ($menu['key'] ?? '') !== '' && $this->missingPath($menu)) {
                    $warnings[] = sprintf(
                        'Menu [%s] in module [%s] has no explicit path; the derived fallback will be used.',
                        (string) $menu['key'],
                        $moduleKey,
                    );
                }

                foreach ($controller['features'] ?? [] as $feature) {
                    $featureKey = (string) ($feature['key'] ?? '');
                    $featureMenu = $feature['menu'] ?? null;

                    if ($featureKey === '' || ! is_string($featureMenu) || $featureMenu === '') {
                        continue;
                    }

                    if ($this->missingPath($feature)) {
                        $warnings[] = sprintf(
                            'Feature [%s] in module [%s] has no explicit path; the FT-2 derived fallback will be used.',
                            $featureKey,
                            $moduleKey,
                        );
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function missingPath(array $node): bool
    {
        return ! isset($node['path']) || $node['path'] === '';
    }

    /**
     * @param  array<string, array<string, mixed>>  $modules
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, list<list<string>>>>, 2: list<string>}
     */
    private function buildAbilityCatalog(array $modules): array
    {
        $policies = [];
        $gates = [];
        $declared = [];
        $policyClasses = [];

        foreach ($modules as $moduleKey => $moduleData) {
            $modulePolicy = $moduleData['authorization']['policy'] ?? null;

            foreach ($moduleData['authorization']['controllers'] ?? [] as $controllerClass => $meta) {
                $requirements = is_array($meta['requirements'] ?? null) ? $meta['requirements'] : [];
                $policyClass = $meta['policy'] ?? $modulePolicy;

                if ($requirements !== [] && ! is_string($policyClass)) {
                    throw new MetadataCompileException(sprintf(
                        'Controller [%s] in module [%s] declares policy abilities but no policy class could be resolved.',
                        $controllerClass,
                        $moduleKey,
                    ));
                }

                if (! is_string($policyClass) || $policyClass === '') {
                    continue;
                }

                $policyKey = KeyIdentifier::fromClass($policyClass);
                $policyClasses[$policyKey] = $policyClass;
                $policies[$policyKey] ??= ['class' => $policyClass, 'models' => []];
                $declared[$policyKey] ??= [];

                foreach ($requirements as $method => $methodRequirements) {
                    $groups = [];

                    foreach ($methodRequirements as $requirement) {
                        $group = [];
                        $model = $requirement['model'] ?? null;

                        foreach ($requirement['abilities'] as $ability) {
                            if (! is_string($ability) || $ability === '') {
                                continue;
                            }

                            $declared[$policyKey][$ability] = true;
                            $group[] = $policyKey.'.'.$ability;

                            if (is_string($model) && $model !== '') {
                                $policies[$policyKey]['models'][$model] ??= ['class' => $model, 'abilities' => []];
                                $policies[$policyKey]['models'][$model]['abilities'][$ability] = true;
                            }
                        }

                        if ($group !== []) {
                            $groups[] = array_values(array_unique($group));
                        }
                    }

                    if ($groups !== []) {
                        $gates[$controllerClass][$method] = $groups;
                    }
                }
            }
        }

        foreach ($policies as $policyKey => &$policy) {
            $models = [];

            foreach ($policy['models'] as $model) {
                $abilities = array_keys($model['abilities']);
                sort($abilities);
                $models[] = ['class' => $model['class'], 'abilities' => $abilities];
            }

            usort($models, static fn (array $left, array $right): int => strcmp((string) $left['class'], (string) $right['class']));
            $policy['models'] = $models;
        }
        unset($policy);

        $warnings = [];

        foreach ($policyClasses as $policyKey => $policyClass) {
            foreach ($this->publicPolicyMethods($policyClass) as $method) {
                if (! isset($declared[$policyKey][$method])) {
                    $warnings[] = sprintf(
                        'Policy [%s] declares public method [%s] that no controller attribute uses; it is ignored.',
                        $policyClass,
                        $method,
                    );
                }
            }
        }

        ksort($policies);
        ksort($gates);

        return [$policies, $gates, $warnings];
    }

    /**
     * @param  class-string  $policyClass
     * @return list<string>
     */
    private function publicPolicyMethods(string $policyClass): array
    {
        if (! class_exists($policyClass)) {
            return [];
        }

        $methods = [];

        foreach ((new \ReflectionClass($policyClass))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $policyClass || $method->getName() === '__construct') {
                continue;
            }

            $file = $method->getFileName();

            if (is_string($file) && str_contains(str_replace('\\', '/', $file), '/vendor/')) {
                continue;
            }

            $methods[] = $method->getName();
        }

        sort($methods);

        return $methods;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function compileModules(?string $modulePath): array
    {
        if ($modulePath !== null) {
            $entry = $this->moduleRegistry->requireByPath($modulePath);
            $existing = $this->store->isPersistent()
                ? ($this->store->read()?->modules ?? [])
                : [];

            $compiled = $this->readEntry($entry, required: true);

            if ($compiled !== null) {
                $existing[$entry->key] = $compiled;
            }

            return $existing;
        }

        $modules = [];

        foreach ($this->moduleRegistry->all() as $entry) {
            $compiled = $this->readEntry($entry);

            if ($compiled !== null) {
                $modules[$entry->key] = $compiled;
            }
        }

        return $modules;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readEntry(ModuleManifestEntry $entry, bool $required = false): ?array
    {
        $hasControllerSignals = $this->hasControllerSignals($entry->rootPath);
        $hasServiceSignals = $this->serviceReader->hasServiceSignals($entry);
        $hasListenerSignals = $this->listenerReader->hasListenerSignals($entry);

        if (! $hasControllerSignals && ! $hasServiceSignals && ! $hasListenerSignals) {
            if ($required) {
                throw new MetadataCompileException(sprintf(
                    'No metadata signals were found for module [%s].',
                    $entry->key,
                ));
            }

            return null;
        }

        if (! $hasControllerSignals) {
            return [
                'controllers' => [],
                'authorization' => ['policy' => null, 'model' => null, 'controllers' => []],
            ];
        }

        return $this->reader->readModule(
            moduleRoot: $entry->rootPath,
            moduleNamespace: $entry->namespace,
            moduleKey: $entry->key,
            moduleEntityName: $this->entityNameForEntry($entry),
        );
    }

    private function hasControllerSignals(string $moduleRoot): bool
    {
        $controllersPath = $moduleRoot.'/Controllers';

        if (! is_dir($controllersPath)) {
            return false;
        }

        return glob($controllersPath.'/*.php') !== [];
    }

    private function entityNameForEntry(ModuleManifestEntry $entry): string
    {
        $segments = explode('\\', $entry->namespace);

        return Str::studly((string) end($segments));
    }
}
