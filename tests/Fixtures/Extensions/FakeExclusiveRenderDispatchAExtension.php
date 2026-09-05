<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Extensions;

use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Extensions\ExtensionBootstrap;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\HookClaim;

final class FakeExclusiveRenderDispatchAExtension implements CoreExtension
{
    public function key(): string
    {
        return 'eventer';
    }

    public function requiredPackages(): array
    {
        return [];
    }

    public function capabilities(): iterable
    {
        yield HookClaim::exclusive(ExtensionHook::RenderDispatch);
    }

    public function register(ExtensionBootstrap $bootstrap): void
    {
        // No-op — conflict is detected at Configure.
    }
}
