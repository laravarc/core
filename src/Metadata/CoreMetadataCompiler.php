<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use DateTimeImmutable;
use Illuminate\Support\Str;
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
    ) {
        $this->pathValidator = $pathValidator ?? new PathValidator;
    }

    public function compile(bool $dryRun = false, ?string $modulePath = null): MetadataCompileResult
    {
        $this->extensions?->dispatch(ExtensionHook::MetadataCompileBefore, [
            'dryRun' => $dryRun,
            'modulePath' => $modulePath,
        ]);

        $modules = $this->compileModules($modulePath);
        ksort($modules);

        $this->assertFeaturesReferenceKnownMenus($modules);
        $this->validateDeclaredPaths($modules);

        $warningModules = $modulePath === null
            ? array_keys($modules)
            : [$this->moduleRegistry->requireByPath($modulePath)->key];

        $warnings = $this->collectPathFallbackWarnings($modules, $warningModules);

        $artifact = new MetadataArtifact(
            modules: $modules,
            compiledAt: (new DateTimeImmutable)->format(DATE_ATOM),
        );

        $menuCount = 0;
        $featureCount = 0;
        $abilityCount = 0;

        foreach ($modules as $moduleData) {
            $menuCount += count($moduleData['menus']);

            foreach ($moduleData['menus'] as $menu) {
                $featureCount += count($menu['features'] ?? []);
            }

            $featureCount += count($moduleData['features']);
            $abilityCount += count($moduleData['policy']['abilities'] ?? []);
        }

        $persisted = false;

        if (! $dryRun && $this->store->isPersistent()) {
            $this->store->write($artifact);
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
    private function validateDeclaredPaths(array $modules): void
    {
        $menuPaths = [];

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['menus'] ?? [] as $menu) {
                $menuKey = $menu['key'] ?? null;

                if (! is_string($menuKey) || $menuKey === '' || ! isset($menu['path'])) {
                    continue;
                }

                try {
                    $validated = $this->pathValidator->validateMenuPath(
                        (string) $menu['path'],
                        'compile',
                    );
                } catch (\InvalidArgumentException $exception) {
                    throw new MetadataCompileException(sprintf(
                        'Menu [%s] in module [%s] has an invalid path: %s',
                        $menuKey,
                        $moduleKey,
                        $exception->getMessage(),
                    ), previous: $exception);
                }

                $menuPaths[$menuKey] = $validated;
            }
        }

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['menus'] ?? [] as $menu) {
                $menuKey = (string) ($menu['key'] ?? '');

                foreach ($menu['features'] ?? [] as $feature) {
                    $this->validateFeaturePath(
                        feature: $feature,
                        menuKey: $menuKey,
                        menuPaths: $menuPaths,
                        moduleKey: (string) $moduleKey,
                    );
                }
            }

            foreach ($moduleData['features'] ?? [] as $feature) {
                $menuKey = $feature['menu'] ?? null;

                $this->validateFeaturePath(
                    feature: $feature,
                    menuKey: is_string($menuKey) ? $menuKey : null,
                    menuPaths: $menuPaths,
                    moduleKey: (string) $moduleKey,
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $feature
     * @param  array<string, string>  $menuPaths
     */
    private function validateFeaturePath(
        array $feature,
        ?string $menuKey,
        array $menuPaths,
        string $moduleKey,
    ): void {
        $featureKey = $feature['key'] ?? null;

        if (! is_string($featureKey) || $featureKey === '' || ! isset($feature['path'])) {
            return;
        }

        $menuPath = $menuKey !== null ? ($menuPaths[$menuKey] ?? null) : null;

        try {
            $this->pathValidator->validateFeatureSuffix(
                (string) $feature['path'],
                $menuPath,
                'compile',
            );
        } catch (\InvalidArgumentException $exception) {
            throw new MetadataCompileException(sprintf(
                'Feature [%s] in module [%s] has an invalid path: %s',
                $featureKey,
                $moduleKey,
                $exception->getMessage(),
            ), previous: $exception);
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

            foreach ($moduleData['menus'] ?? [] as $menu) {
                $menuKey = (string) ($menu['key'] ?? '');

                if ($menuKey !== '' && $this->missingPath($menu)) {
                    $warnings[] = sprintf(
                        'Menu [%s] in module [%s] has no explicit path; the derived fallback will be used.',
                        $menuKey,
                        $moduleKey,
                    );
                }

                foreach ($menu['features'] ?? [] as $feature) {
                    $featureKey = (string) ($feature['key'] ?? '');

                    if ($featureKey !== '' && $this->missingPath($feature)) {
                        $warnings[] = sprintf(
                            'Feature [%s] in module [%s] has no explicit path; the FT-2 derived fallback will be used.',
                            $featureKey,
                            $moduleKey,
                        );
                    }
                }
            }

            foreach ($moduleData['features'] ?? [] as $feature) {
                $featureKey = (string) ($feature['key'] ?? '');
                $menuKey = $feature['menu'] ?? null;

                if ($featureKey === '' || ! is_string($menuKey) || $menuKey === '') {
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
     */
    private function assertFeaturesReferenceKnownMenus(array $modules): void
    {
        $knownMenuKeys = [];

        foreach ($modules as $moduleData) {
            foreach ($moduleData['menus'] ?? [] as $menu) {
                $key = $menu['key'] ?? null;

                if (is_string($key) && $key !== '') {
                    $knownMenuKeys[$key] = true;
                }
            }
        }

        foreach ($modules as $moduleKey => $moduleData) {
            foreach ($moduleData['features'] ?? [] as $feature) {
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

            $existing[$entry->key] = $this->readEntry($entry, required: true);

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

        $compiled = $hasControllerSignals
            ? $this->reader->readModule(
                moduleRoot: $entry->rootPath,
                moduleNamespace: $entry->namespace,
                moduleKey: $entry->key,
                moduleEntityName: $this->entityNameForEntry($entry),
            )
            : [
                'menus' => [],
                'features' => [],
                'policy' => [
                    'model' => null,
                    'policy' => null,
                    'abilities' => [],
                    'ability_overrides' => [],
                    'controllers' => [],
                ],
            ];

        $compiled['services'] = $this->serviceReader->readModule($entry);
        $compiled['listeners'] = $this->listenerReader->readModule($entry);

        return $compiled;
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
