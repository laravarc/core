<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Contracts\ModuleServiceProviderContract;
use Laravarc\Core\Tests\Fixtures\Extensions\TestPushedExtension;

final class TestPushExtensionModuleServiceProvider extends ServiceProvider implements ModuleServiceProviderContract
{
    public static function modulePath(): string
    {
        return 'Platform/PushTest';
    }

    public function register(): void
    {
        config()->push('laravarc.extensions', TestPushedExtension::class);
    }
}
