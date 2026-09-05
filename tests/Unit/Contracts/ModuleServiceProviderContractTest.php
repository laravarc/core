<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Contracts\ModuleServiceProviderContract;
use Laravarc\Core\Tests\Fixtures\Providers\FakeModuleServiceProvider;

describe('ModuleServiceProviderContract', function () {
    it('exists with static modulePath method', function () {
        expect(interface_exists(ModuleServiceProviderContract::class))->toBeTrue();

        $method = new ReflectionMethod(ModuleServiceProviderContract::class, 'modulePath');

        expect($method->isStatic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('string');
    });

    it('is satisfied by a ServiceProvider implementation', function () {
        expect(is_subclass_of(FakeModuleServiceProvider::class, ServiceProvider::class))->toBeTrue()
            ->and(in_array(ModuleServiceProviderContract::class, class_implements(FakeModuleServiceProvider::class), true))->toBeTrue()
            ->and(FakeModuleServiceProvider::modulePath())->toBe('Admin/Platform/Catalog');
    });
});
