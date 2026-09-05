<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Support;

use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLayout;

final class ModuleGenerationState
{
    public function needsFullGeneration(ModuleIdentity $identity): bool
    {
        if (! $identity->existsOnFilesystem()) {
            return false;
        }

        foreach (ModuleLayout::discoverySignalPaths() as $signal) {
            if ($signal === ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS) {
                continue;
            }

            $path = $identity->rootPath.'/'.$signal;

            if ($this->directoryHasPhpFiles($path)) {
                return false;
            }
        }

        return $this->directoryHasPhpFiles(
            $identity->rootPath.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS,
        );
    }

    private function directoryHasPhpFiles(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $files = glob($path.'/*.php');

        return is_array($files) && $files !== [];
    }
}
