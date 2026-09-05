<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Extensions;

use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Extensions\ExtensionBootstrap;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\HookClaim;

final class FakeBroadcastGenerationBeforeAExtension implements CoreExtension
{
    /** @var list<string> */
    public static array $activated = [];

    public function key(): string
    {
        return 'fake-broadcast-a';
    }

    public function requiredPackages(): array
    {
        return [];
    }

    public function capabilities(): iterable
    {
        yield HookClaim::broadcast(ExtensionHook::GenerationBefore);
    }

    public function register(ExtensionBootstrap $bootstrap): void
    {
        $bootstrap->listen(ExtensionHook::GenerationBefore, static function (): void {
            self::$activated[] = 'fake-broadcast-a';
        });
    }

    public static function reset(): void
    {
        self::$activated = [];
    }
}
