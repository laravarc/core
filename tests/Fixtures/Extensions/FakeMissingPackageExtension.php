<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Extensions;

use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Extensions\ExtensionBootstrap;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\HookClaim;

final class FakeMissingPackageExtension implements CoreExtension
{
    public function key(): string
    {
        return 'fake-missing-package';
    }

    public function requiredPackages(): array
    {
        return ['vendor/nonexistent-package'];
    }

    public function capabilities(): iterable
    {
        yield HookClaim::broadcast(ExtensionHook::CacheRefresh);
    }

    public function register(ExtensionBootstrap $bootstrap): void
    {
        // No-op.
    }
}
