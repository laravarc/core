<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Extensions\ExtensionPackageChecker;
use Laravarc\Core\Tests\Fixtures\Extensions\TestPushedExtension;
use Laravarc\Core\Tests\Fixtures\Providers\TestPushExtensionModuleServiceProvider;

describe('ModuleProviderExtensionRegistration', function () {
    beforeEach(function () {
        TestPushedExtension::reset();
    });

    it('sees extensions pushed during module service provider register when configure runs after loader order', function () {
        $config = new Repository([
            'laravarc' => [
                'extensions' => [],
            ],
        ]);

        /** @var Application $app */
        $app = Container::getInstance();
        $app->instance('config', $config);

        $app->register(TestPushExtensionModuleServiceProvider::class);

        $manager = new ExtensionManager($app, new ExtensionPackageChecker);
        $manager->configure($config->get('laravarc.extensions'));

        expect($manager->isConfigured())->toBeTrue();

        $manager->dispatch(ExtensionHook::CacheRefresh);

        expect(TestPushedExtension::$registered)->toBeTrue();
    });

    it('misses extensions when extension manager is configured before module service provider register', function () {
        $config = new Repository([
            'laravarc' => [
                'extensions' => [],
            ],
        ]);

        /** @var Application $app */
        $app = Container::getInstance();
        $app->instance('config', $config);

        $manager = new ExtensionManager($app, new ExtensionPackageChecker);
        $manager->configure($config->get('laravarc.extensions'));

        $app->register(TestPushExtensionModuleServiceProvider::class);

        $manager->dispatch(ExtensionHook::CacheRefresh);

        expect(TestPushedExtension::$registered)->toBeFalse();
    });
});
