<?php

declare(strict_types=1);

use Laravarc\Core\Catalog\CatalogState;
use Laravarc\Core\Support\CachePath;

describe('CatalogState', function () {
    afterEach(function () {
        $path = CachePath::catalogState();
        if (is_file($path)) {
            unlink($path);
        }
        CachePath::useBaseDirectory(null);
    });

    it('defaults to not installed when the state file is absent', function () {
        $state = new CatalogState;

        expect($state->isInstalled())->toBeFalse()
            ->and($state->revision())->toBe(0)
            ->and($state->read()['installed_at'])->toBeNull();
    });

    it('round-trips installed and revision through the state file', function () {
        $state = new CatalogState;
        $state->markInstalled();
        $state->bumpRevision();

        expect($state->isInstalled())->toBeTrue()
            ->and($state->revision())->toBe(1)
            ->and(is_file($state->path()))->toBeTrue();
    });

    it('restores not installed after markUninstalled', function () {
        $state = new CatalogState;
        $state->markInstalled();
        $state->markUninstalled();

        expect($state->isInstalled())->toBeFalse()
            ->and(is_file($state->path()))->toBeFalse();
    });

    it('does not bump revision while not installed', function () {
        $state = new CatalogState;
        $state->bumpRevision();

        expect($state->revision())->toBe(0);
    });

    it('writes nested catalog payload and reads legacy flat root keys', function () {
        $state = new CatalogState;
        $state->markInstalled();
        $state->bumpRevision();

        $contents = file_get_contents($state->path());
        expect($contents)->toContain("'catalog'")
            ->and($contents)->toContain("'installed'");

        $legacyPath = $state->path();
        file_put_contents($legacyPath, <<<'PHP'
<?php

declare(strict_types=1);

return array(
  'installed' => true,
  'revision' => 42,
  'installed_at' => '2026-01-01T00:00:00+00:00',
);

PHP);

        expect($state->revision())->toBe(42)
            ->and($state->isInstalled())->toBeTrue();

        $state->bumpRevision();

        $migrated = file_get_contents($legacyPath);
        expect($migrated)->toContain("'catalog'")
            ->and($state->revision())->toBe(43);
    });
});
