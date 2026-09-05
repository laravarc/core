<?php

declare(strict_types=1);

namespace Laravarc\Core\Surfacer;

use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Extensions\ExtensionBootstrap;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\HookClaim;
use Laravarc\Surfacer\Contracts\SurfaceRepository;
use Laravarc\Surfacer\Registry\CachedSurfaceRepository;
use Laravarc\Surfacer\Support\RateLimiterRegistrar;

/**
 * Bridge: when listed in config('laravarc.extensions'), syncs Surfacer's
 * resolved-surface cache with laravarc:cache refresh/clear.
 *
 * Soft-depends on laravarc/surfacer — register only after composer require.
 * Routing wrap lives in ModuleRouteLoader (soft class_exists), not here.
 */
final class SurfacerCoreExtension implements CoreExtension
{
    public function key(): string
    {
        return 'surfacer';
    }

    public function requiredPackages(): array
    {
        return ['laravarc/surfacer'];
    }

    public function capabilities(): iterable
    {
        yield HookClaim::broadcast(ExtensionHook::CacheRefresh);
        yield HookClaim::broadcast(ExtensionHook::CacheClear);
    }

    public function register(ExtensionBootstrap $bootstrap): void
    {
        if (! interface_exists(SurfaceRepository::class)) {
            return;
        }

        $bootstrap->listen(ExtensionHook::CacheRefresh, static function (): void {
            if (! class_exists(CachedSurfaceRepository::class)) {
                return;
            }

            /** @var CachedSurfaceRepository $repository */
            $repository = app(CachedSurfaceRepository::class);
            $surfaces = $repository->refresh();

            if (class_exists(RateLimiterRegistrar::class)) {
                app(RateLimiterRegistrar::class)->register($surfaces);
            }
        });

        $bootstrap->listen(ExtensionHook::CacheClear, static function (): void {
            if (! class_exists(CachedSurfaceRepository::class)) {
                return;
            }

            app(CachedSurfaceRepository::class)->clearCache();
        });
    }
}
