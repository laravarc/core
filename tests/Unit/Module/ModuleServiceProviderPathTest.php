<?php

declare(strict_types=1);

use Laravarc\Core\Module\ModuleServiceProviderPath;

describe('ModuleServiceProviderPath', function () {
    it('derives module path from provider class', function () {
        expect(ModuleServiceProviderPath::forClass(
            'App\\Modules\\Admin\\Platform\\Catalog\\Providers\\CatalogServiceProvider',
        ))->toBe('Admin/Platform/Catalog')
            ->and(ModuleServiceProviderPath::forClass(
                'App\\Modules\\Admin\\Platform\\Menu\\Providers\\MenuServiceProvider',
            ))->toBe('Admin/Platform/Menu');
    });

    it('rejects classes outside module namespace', function () {
        expect(fn () => ModuleServiceProviderPath::forClass(stdClass::class))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects classes not under Providers segment', function () {
        expect(fn () => ModuleServiceProviderPath::forClass(
            'App\\Modules\\Admin\\Platform\\Catalog\\Controllers\\CatalogController',
        ))->toThrow(InvalidArgumentException::class);
    });
});
