<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Concerns;

trait ConfirmsModuleRelocation
{
    protected function confirmModuleRelocation(string $sourcePath, string $targetPath): bool
    {
        if ($this->isForce()) {
            return true;
        }

        return $this->confirm(sprintf(
            'Relocate module [%s] to [%s]?',
            trim($sourcePath, '/'),
            trim($targetPath, '/'),
        ), false);
    }

    protected function printRelocationWarnings(): void
    {
        $this->newLine();
        $this->warn('Config files may contain hardcoded FQCN strings referencing the old namespace.');
        $this->warn('These are NOT auto-detected by namespace search-replace. Please review manually.');
        $this->newLine();
        $this->warn('Database table names and migration files were NOT changed — only code structure and namespace.');
    }
}
