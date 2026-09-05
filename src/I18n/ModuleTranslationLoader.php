<?php

declare(strict_types=1);

namespace Laravarc\Core\I18n;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleLayout;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Registers module-local and Shared translation namespaces at boot.
 *
 * - Modules/{Path}/Lang/{locale} → __('module.key::file.line')
 * - Shared/{Path}/Langs/{locale} → __('shared.module.key::file.line')
 */
final class ModuleTranslationLoader
{
    public const SHARED_NAMESPACE_PREFIX = 'shared.';

    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly string $modulesPath,
        private readonly string $sharedPath,
        private readonly bool $loadModuleTranslations,
        private readonly bool $loadSharedTranslations,
    ) {}

    public function load(Translator $translator): void
    {
        if ($this->loadModuleTranslations) {
            $this->registerModuleNamespaces($translator);
        }

        if ($this->loadSharedTranslations) {
            $this->registerSharedNamespaces($translator);
        }
    }

    /**
     * @return list<array{namespace: string, path: string}>
     */
    public function discovered(): array
    {
        $entries = [];

        if ($this->loadModuleTranslations && is_dir($this->modulesPath)) {
            foreach ($this->moduleRegistry->all() as $module) {
                $langPath = $module->rootPath.'/'.ModuleLayout::LANG;

                if (is_dir($langPath)) {
                    $entries[] = [
                        'namespace' => $module->key,
                        'path' => $langPath,
                    ];
                }
            }
        }

        if ($this->loadSharedTranslations && is_dir($this->sharedPath)) {
            foreach ($this->discoverSharedLangRoots() as $relativePath => $langPath) {
                $entries[] = [
                    'namespace' => $this->sharedNamespace($relativePath),
                    'path' => $langPath,
                ];
            }
        }

        return $entries;
    }

    /**************************************************************
     *                     HELPER FUNCTIONS                       *
     **************************************************************/

    private function registerModuleNamespaces(Translator $translator): void
    {
        if (! is_dir($this->modulesPath)) {
            return;
        }

        foreach ($this->moduleRegistry->all() as $module) {
            $langPath = $module->rootPath.'/'.ModuleLayout::LANG;

            if (! is_dir($langPath)) {
                continue;
            }

            $translator->addNamespace($module->key, $langPath);
        }
    }

    private function registerSharedNamespaces(Translator $translator): void
    {
        if (! is_dir($this->sharedPath)) {
            return;
        }

        foreach ($this->discoverSharedLangRoots() as $relativePath => $langPath) {
            $translator->addNamespace($this->sharedNamespace($relativePath), $langPath);
        }
    }

    /**
     * @return array<string, string> relative module path => absolute Langs path
     */
    private function discoverSharedLangRoots(): array
    {
        $sharedRoot = realpath($this->sharedPath);

        if ($sharedRoot === false) {
            return [];
        }

        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $sharedRoot,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS,
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (! $item->isDir() || $item->getFilename() !== 'Langs') {
                continue;
            }

            $moduleRoot = dirname($item->getPathname());
            $relative = ltrim(str_replace('\\', '/', substr($moduleRoot, strlen($sharedRoot))), '/');

            if ($relative === '') {
                continue;
            }

            $found[$relative] = $item->getPathname();
        }

        ksort($found);

        return $found;
    }

    private function sharedNamespace(string $relativeModulePath): string
    {
        $segments = preg_split('#[\\\\/]+#', trim($relativeModulePath, '/\\')) ?: [];

        $dotted = collect($segments)
            ->filter(static fn (string $segment): bool => $segment !== '')
            ->map(static fn (string $segment): string => Str::kebab($segment))
            ->implode('.');

        return self::SHARED_NAMESPACE_PREFIX.$dotted;
    }
}
