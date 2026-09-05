<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use Illuminate\Contracts\Foundation\Application;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Metadata\Exceptions\MetadataArtifactNotFoundException;

final class CoreServiceRegistrar
{
    public function __construct(
        private readonly Application $app,
        private readonly MetadataReader $metadataReader,
    ) {}

    public function register(): void
    {
        try {
            $modules = $this->metadataReader->artifact()->modules;
        } catch (MetadataArtifactNotFoundException) {
            return;
        }

        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            foreach ($module['services'] ?? [] as $binding) {
                if (! is_array($binding)) {
                    continue;
                }

                $this->registerBinding(
                    contract: $binding['contract'] ?? null,
                    concrete: $binding['concrete'] ?? null,
                );
            }
        }
    }

    private function registerBinding(mixed $contract, mixed $concrete): void
    {
        if (! is_string($contract) || $contract === '' || ! $this->interfaceLoadable($contract)) {
            return;
        }

        if (! is_string($concrete) || $concrete === '' || ! $this->classLoadable($concrete)) {
            return;
        }

        $this->app->bind($contract, $concrete);
    }

    /**
     * Stale compiled metadata may reference a deleted Contract file; Composer
     * classmap include can throw — treat as "no binding" (YAGNI / Decision 034).
     */
    private function interfaceLoadable(string $contract): bool
    {
        try {
            return interface_exists($contract);
        } catch (\Throwable) {
            return false;
        }
    }

    private function classLoadable(string $concrete): bool
    {
        try {
            return class_exists($concrete);
        } catch (\Throwable) {
            return false;
        }
    }
}
