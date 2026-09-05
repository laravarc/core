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

final class CoreMetadataCompiler implements MetadataCompiler
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ReflectionMetadataReader $reader,
        private readonly ServiceMetadataReader $serviceReader,
        private readonly ListenerMetadataReader $listenerReader,
        private readonly MetadataArtifactStore $store,
        private readonly ?ExtensionManager $extensions = null,
    ) {}

    public function compile(bool $dryRun = false, ?string $modulePath = null): MetadataCompileResult
    {
        $this->extensions?->dispatch(ExtensionHook::MetadataCompileBefore, [
            'dryRun' => $dryRun,
            'modulePath' => $modulePath,
        ]);

        $modules = $this->compileModules($modulePath);
        ksort($modules);

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
        );

        $this->extensions?->dispatch(ExtensionHook::MetadataCompileAfter, $result);

        return $result;
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
