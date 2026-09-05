<?php

declare(strict_types=1);

namespace Laravarc\Core\Routing;

use Illuminate\Support\Facades\View;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleLayout;

final class ModuleViewLoader
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly string $modulesPath,
        private readonly bool $enabled,
    ) {}

    public function load(): void
    {
        if (! $this->enabled || ! is_dir($this->modulesPath)) {
            return;
        }

        foreach ($this->moduleRegistry->all() as $entry) {
            $viewsDirectory = $entry->rootPath.'/'.ModuleLayout::VIEWS;

            if (! is_dir($viewsDirectory)) {
                continue;
            }

            View::addNamespace($entry->key, $viewsDirectory);
        }
    }
}
