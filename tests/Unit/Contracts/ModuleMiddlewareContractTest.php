<?php

declare(strict_types=1);

use Laravarc\Core\Contracts\ModuleMiddlewareContract;
use Laravarc\Core\Tests\Fixtures\Middlewares\FakeModuleMiddleware;

describe('ModuleMiddlewareContract', function () {
    it('declares a static alias method', function () {
        expect(interface_exists(ModuleMiddlewareContract::class))->toBeTrue();

        $method = new ReflectionMethod(ModuleMiddlewareContract::class, 'alias');

        expect($method->isStatic())->toBeTrue()
            ->and((string) $method->getReturnType())->toBe('string');
    });

    it('is implemented by fixture middleware with a stable alias', function () {
        expect(in_array(ModuleMiddlewareContract::class, class_implements(FakeModuleMiddleware::class), true))->toBeTrue()
            ->and(FakeModuleMiddleware::alias())->toBe('fixture.middleware');
    });
});
