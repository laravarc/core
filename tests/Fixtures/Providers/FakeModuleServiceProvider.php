<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Contracts\ModuleServiceProviderContract;

/**
 * Example module primary SP — satisfies {@see ModuleServiceProviderContract}.
 */
final class FakeModuleServiceProvider extends ServiceProvider implements ModuleServiceProviderContract
{
    public static function modulePath(): string
    {
        return 'Admin/Platform/Catalog';
    }
}
