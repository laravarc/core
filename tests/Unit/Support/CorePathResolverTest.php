<?php

declare(strict_types=1);

use Laravarc\Core\Support\CorePathResolver;

describe('CorePathResolver', function () {
    it('resolves relative paths against the application directory', function () {
        expect(CorePathResolver::resolve('Shared'))->toBe(app_path('Shared'));
    });

    it('keeps absolute paths unchanged', function () {
        $absolute = app_path('Custom/Shared');

        expect(CorePathResolver::resolve($absolute))->toBe($absolute);
    });

    it('builds namespace segments from a shared directory path', function () {
        expect(CorePathResolver::namespaceFromPath(app_path('Shared')))->toBe('Shared')
            ->and(CorePathResolver::namespaceFromPath(app_path('Platform/Contracts')))->toBe('Platform\\Contracts');
    });
});
