<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Concerns;

use Laravarc\Core\Module\ModulePathValidator;

trait ConfirmsModuleRemoval
{
    protected function confirmModuleRemoval(string $path): bool
    {
        if ($this->isForce()) {
            return true;
        }

        $normalized = implode('/', (new ModulePathValidator)->normalize(trim($path, '/')));

        if (! $this->confirm(sprintf('Remove module [%s]? This cannot be undone.', $normalized), false)) {
            return false;
        }

        $typed = trim((string) $this->ask(sprintf('Type the module path to confirm deletion [%s]', $normalized)));

        return $typed === $normalized;
    }
}
