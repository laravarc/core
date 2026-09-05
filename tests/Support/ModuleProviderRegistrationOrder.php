<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Support;

final class ModuleProviderRegistrationOrder
{
    /** @var list<string> */
    public static array $order = [];

    public static function reset(): void
    {
        self::$order = [];
    }
}
