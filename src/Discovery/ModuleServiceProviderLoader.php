<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use Illuminate\Contracts\Foundation\Application;

final class ModuleServiceProviderLoader
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly Application $app,
        private readonly bool $enabled,
    ) {}

    public function load(): void
    {
        if (! $this->enabled) {
            return;
        }

        $entries = $this->moduleRegistry->all();

        usort(
            $entries,
            static fn (ModuleManifestEntry $left, ModuleManifestEntry $right): int => strcmp($left->path, $right->path),
        );

        foreach ($entries as $entry) {
            foreach ($entry->providers as $providerClass) {
                $this->app->register($providerClass);
            }
        }
    }
}
