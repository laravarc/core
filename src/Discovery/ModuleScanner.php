<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use Laravarc\Core\Contracts\ModuleKeyResolver;
use Laravarc\Core\Discovery\Exceptions\ModuleScanException;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLayout;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModuleScanner
{
    public function __construct(
        private readonly ModuleKeyResolver $moduleKeyResolver,
        private readonly ModuleServiceProviderResolver $serviceProviderResolver,
    ) {}

    /**
     * @return list<ModuleManifestEntry>
     */
    public function scan(string $modulesPath, string $moduleNamespace, string $discoveredAt): array
    {
        if (! is_dir($modulesPath)) {
            throw new ModuleScanException(sprintf(
                'Modules path [%s] does not exist or is not readable.',
                $modulesPath,
            ));
        }

        if (! is_readable($modulesPath)) {
            throw new ModuleScanException(sprintf(
                'Modules path [%s] is not readable.',
                $modulesPath,
            ));
        }

        $modulesRoot = realpath($modulesPath);

        if ($modulesRoot === false) {
            throw new ModuleScanException(sprintf(
                'Modules path [%s] could not be resolved.',
                $modulesPath,
            ));
        }

        $entries = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $modulesRoot,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS,
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (! $item->isDir()) {
                continue;
            }

            $directory = $item->getPathname();

            if (! $this->isWithinModulesRoot($directory, $modulesRoot)) {
                continue;
            }

            if (! $this->hasStructuralSignals($directory)) {
                continue;
            }

            $relativePath = $this->relativePath($directory, $modulesRoot);

            if ($relativePath === '') {
                continue;
            }

            $identity = ModuleIdentity::fromPath(
                path: $relativePath,
                modulesPath: $modulesRoot,
                moduleNamespace: $moduleNamespace,
                rootPathOverride: $directory,
            );

            $entries[] = new ModuleManifestEntry(
                path: $identity->path,
                key: $this->moduleKeyResolver->resolve($identity),
                namespace: $identity->namespace,
                rootPath: $identity->rootPath,
                discoveredAt: $discoveredAt,
                providers: $this->serviceProviderResolver->resolve($identity),
            );
        }

        usort(
            $entries,
            static fn (ModuleManifestEntry $left, ModuleManifestEntry $right): int => strcmp($left->path, $right->path),
        );

        return $entries;
    }

    private function hasStructuralSignals(string $directory): bool
    {
        foreach (ModuleLayout::discoverySignalPaths() as $relativeSignalPath) {
            if (is_dir($directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeSignalPath))) {
                return true;
            }
        }

        return false;
    }

    private function isWithinModulesRoot(string $directory, string $modulesRoot): bool
    {
        $resolvedDirectory = realpath($directory);
        $resolvedModulesRoot = realpath($modulesRoot);

        if ($resolvedDirectory === false || $resolvedModulesRoot === false) {
            return false;
        }

        $normalizedDirectory = rtrim(str_replace('\\', '/', $resolvedDirectory), '/');
        $normalizedModulesRoot = rtrim(str_replace('\\', '/', $resolvedModulesRoot), '/');

        return $normalizedDirectory === $normalizedModulesRoot
            || str_starts_with($normalizedDirectory.'/', $normalizedModulesRoot.'/');
    }

    private function relativePath(string $directory, string $modulesRoot): string
    {
        $resolvedDirectory = realpath($directory);
        $resolvedModulesRoot = realpath($modulesRoot);

        if ($resolvedDirectory === false || $resolvedModulesRoot === false) {
            return '';
        }

        $relative = substr($resolvedDirectory, strlen($resolvedModulesRoot));
        $relative = trim(str_replace('\\', '/', $relative), '/');

        return $relative;
    }
}
