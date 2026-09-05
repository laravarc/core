<?php

declare(strict_types=1);

namespace Laravarc\Core\Routing;

use Laravarc\Core\Discovery\ModuleManifestEntry;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Surfacer\RootSurfaceLocator;
use Laravarc\Surfacer\Contracts\SurfaceRepository;
use Laravarc\Surfacer\Surfacer;

final class ModuleRouteLoader
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly string $modulesPath,
        private readonly bool $enabled,
        private readonly RootSurfaceLocator $surfaceLocator = new RootSurfaceLocator,
    ) {}

    public function load(): void
    {
        if (! $this->enabled || ! is_dir($this->modulesPath)) {
            return;
        }

        $entries = $this->moduleRegistry->all();

        usort($entries, static fn ($left, $right) => strcmp($left->path, $right->path));

        $byRoot = [];
        foreach ($entries as $entry) {
            $root = $this->surfaceLocator->rootSegmentFromModulePath($entry->path);
            $byRoot[$root][] = $entry;
        }

        foreach ($byRoot as $rootSegment => $rootEntries) {
            $this->loadRootGroup((string) $rootSegment, $rootEntries);
        }
    }

    /**
     * @param  list<ModuleManifestEntry>  $entries
     */
    private function loadRootGroup(string $rootSegment, array $entries): void
    {
        $surfaceName = $this->resolveSurfaceName($rootSegment);

        if ($surfaceName !== null && $this->surfacerAvailable()) {
            app(Surfacer::class)->group($surfaceName, function () use ($entries): void {
                $this->requireRouteFiles($entries);
            });

            return;
        }

        $this->requireRouteFiles($entries);
    }

    private function resolveSurfaceName(string $rootSegment): ?string
    {
        if (! $this->surfaceLocator->hasSurface($this->modulesPath, $rootSegment)) {
            return null;
        }

        $name = $this->surfaceLocator->surfaceNameForRoot($this->modulesPath, $rootSegment);
        if ($name === null) {
            return null;
        }

        if (
            interface_exists(SurfaceRepository::class)
            && app()->bound(SurfaceRepository::class)
            && ! app(SurfaceRepository::class)->has($name)
        ) {
            // Surface file exists on disk but is not in the Surfacer repository
            // (definitions_path misconfigured) — fall back to legacy loading.
            return null;
        }

        return $name;
    }

    private function surfacerAvailable(): bool
    {
        return interface_exists(SurfaceRepository::class)
            && class_exists(Surfacer::class);
    }

    /**
     * @param  list<ModuleManifestEntry>  $entries
     */
    private function requireRouteFiles(array $entries): void
    {
        foreach ($entries as $entry) {
            $routesDirectory = $entry->rootPath.'/'.ModuleLayout::ROUTES;

            if (! is_dir($routesDirectory)) {
                continue;
            }

            foreach (glob($routesDirectory.'/*Route.php') ?: [] as $routeFile) {
                if (is_file($routeFile)) {
                    require $routeFile;
                }
            }
        }
    }
}
