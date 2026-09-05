<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Illuminate\Filesystem\Filesystem;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLifecycleGuard;

final class ModuleRemover
{
    public function __construct(
        private readonly ModuleLifecycleGuard $lifecycleGuard,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly Filesystem $filesystem,
    ) {}

    public function remove(ModuleIdentity $identity, bool $dryRun = false): void
    {
        $this->lifecycleGuard->assertCanRemove($identity);

        if ($dryRun) {
            return;
        }

        $this->filesystem->deleteDirectory($identity->rootPath);
        $this->moduleRegistry->refresh();
    }
}
