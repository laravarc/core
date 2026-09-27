<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use Illuminate\Contracts\Foundation\Application;
use Laravarc\Core\Discovery\Exceptions\ModuleScanException;

final class ModuleMiddlewareLoader
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

        /** @var array<string, class-string> $seenAliases */
        $seenAliases = [];
        $router = $this->app->make('router');

        foreach ($entries as $entry) {
            foreach ($entry->middlewares as $middlewareClass) {
                $alias = $middlewareClass::alias();

                if (isset($seenAliases[$alias])) {
                    throw new ModuleScanException(sprintf(
                        'Duplicate middleware alias [%s] from [%s] (already registered by [%s]).',
                        $alias,
                        $middlewareClass,
                        $seenAliases[$alias],
                    ));
                }

                $seenAliases[$alias] = $middlewareClass;
                $router->aliasMiddleware($alias, $middlewareClass);
            }
        }
    }
}
