<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Extensions;

use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Extensions\ExtensionBootstrap;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\HookClaim;

final class FakeHookExtension implements CoreExtension
{
    /** @var list<string> */
    public static array $hooks = [];

    public function key(): string
    {
        return 'fake-hook';
    }

    public function requiredPackages(): array
    {
        return [];
    }

    public function capabilities(): iterable
    {
        yield HookClaim::broadcast(ExtensionHook::GenerationBefore);
        yield HookClaim::broadcast(ExtensionHook::CacheRefresh);
    }

    public function register(ExtensionBootstrap $bootstrap): void
    {
        $bootstrap->listen(ExtensionHook::GenerationBefore, function (): void {
            self::$hooks[] = 'generation:before';
        });

        $bootstrap->listen(ExtensionHook::CacheRefresh, function (): void {
            self::$hooks[] = 'cache:refresh';
        });
    }

    public static function reset(): void
    {
        self::$hooks = [];
    }
}
