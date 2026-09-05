<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorizer;

use Laravarc\Authorizer\Contracts\AbilityRegistry;
use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Extensions\ExtensionBootstrap;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\HookClaim;

/**
 * Bridge: when listed in config('laravarc.extensions'), rebinds Authorizer's
 * AbilityRegistry to the metadata-backed implementation.
 *
 * Soft-depends on laravarc/authorizer — register only after composer require.
 */
final class AuthorizerCoreExtension implements CoreExtension
{
    public function key(): string
    {
        return 'authorizer';
    }

    public function requiredPackages(): array
    {
        return ['laravarc/authorizer'];
    }

    public function capabilities(): iterable
    {
        yield HookClaim::broadcast(ExtensionHook::CacheRefresh);
        yield HookClaim::broadcast(ExtensionHook::GenerationAfter);
    }

    public function register(ExtensionBootstrap $bootstrap): void
    {
        if (! interface_exists(AbilityRegistry::class)) {
            return;
        }

        $container = app();

        $container->singleton(AbilityRegistry::class, static function ($app) {
            return new MetadataAbilityRegistry(
                $app->make(MetadataReader::class),
            );
        });

        $bootstrap->listen(ExtensionHook::CacheRefresh, static function () use ($container): void {
            $container->forgetInstance(AbilityRegistry::class);
        });
    }
}
