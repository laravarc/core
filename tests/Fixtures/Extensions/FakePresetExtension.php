<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Extensions;

use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Extensions\ExtensionBootstrap;

final class FakePresetExtension implements CoreExtension
{
    public function key(): string
    {
        return 'fake-preset';
    }

    public function requiredPackages(): array
    {
        return [];
    }

    public function capabilities(): iterable
    {
        return [];
    }

    public function register(ExtensionBootstrap $bootstrap): void
    {
        $bootstrap->addPreset('fake-preset', ['custom-generator']);
    }
}
