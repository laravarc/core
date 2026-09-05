<?php

declare(strict_types=1);

namespace Laravarc\Core\Database;

use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleLayout;

/**
 * Collects Database/Migrations paths from discovered modules for Laravel migrator.
 */
final class ModuleMigrationLoader
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly string $modulesPath,
        private readonly bool $enabled,
    ) {}

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        if (! $this->enabled || ! is_dir($this->modulesPath)) {
            return [];
        }

        $paths = [];

        foreach ($this->moduleRegistry->all() as $module) {
            $migrationsPath = $module->rootPath.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS;

            if (is_dir($migrationsPath)) {
                $paths[] = $migrationsPath;
            }
        }

        return $paths;
    }
}
