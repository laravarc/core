<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Extensions;

use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Extensions\ExtensionBootstrap;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\HookClaim;

final class TestPushedExtension implements CoreExtension
{
    public static bool $registered = false;

    public function key(): string
    {
        return 'test.pushed';
    }

    public function requiredPackages(): array
    {
        return [];
    }

    public function capabilities(): iterable
    {
        yield HookClaim::broadcast(ExtensionHook::CacheRefresh);
    }

    public function register(ExtensionBootstrap $bootstrap): void
    {
        $bootstrap->listen(ExtensionHook::CacheRefresh, function (): void {
            self::$registered = true;
        });
    }

    public static function reset(): void
    {
        self::$registered = false;
    }
}
