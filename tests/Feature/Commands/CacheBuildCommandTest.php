<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Laravarc\Core\Authorization\AbilityCatalogReader;
use Laravarc\Core\Authorizer\MetadataAbilityRegistry;
use Laravarc\Core\Metadata\Exceptions\CorruptMetadataArtifactException;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;
use Laravarc\Core\Metadata\Stores\FileMetadataArtifactStore;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Support\CachePath;

describe('laravarc:cache build', function () {
    beforeEach(function () {
        $modulesPath = (string) config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/Platform/Build';
        $namespace = 'App\\Modules\\Platform\\Build';

        mkdir($moduleRoot.'/Models', 0777, true);
        mkdir($moduleRoot.'/Policies', 0777, true);
        mkdir($moduleRoot.'/Controllers', 0777, true);

        file_put_contents($moduleRoot.'/Models/Widget.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Models;

final class Widget {}
PHP);

        file_put_contents($moduleRoot.'/Policies/WidgetPolicy.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Policies;

use Illuminate\\Auth\\Access\\HandlesAuthorization;

final class WidgetPolicy
{
    use HandlesAuthorization;

    public function viewAny(?object \$user): bool
    {
        return true;
    }

    public function view(?object \$user): bool
    {
        return true;
    }
}
PHP);

        file_put_contents($moduleRoot.'/Controllers/WidgetController.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Controllers;

use Laravarc\\Core\\Metadata\\Attributes\\Menu;
use Laravarc\\Core\\Metadata\\Attributes\\Policy;

#[Menu(key: 'platform.widgets', label: 'Widgets', path: '/widgets')]
final class WidgetController
{
    #[Policy(ability: 'viewAny', model: \\{$namespace}\\Models\\Widget::class)]
    public function index(): string
    {
        return 'ok';
    }
}
PHP);

        file_put_contents($moduleRoot.'/Controllers/WidgetTabController.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Controllers;

use Laravarc\\Core\\Metadata\\Attributes\\Feature;

#[Feature(key: 'platform.widgets.tab', label: 'Tab', menu: 'platform.widgets', placement: 'tab', path: '/tab')]
final class WidgetTabController {}
PHP);
    });

    it('writes manifest, metadata and ability catalog in one build', function () {
        expect(Artisan::call('laravarc:cache', ['action' => 'build']))->toBe(0);

        $manifestPath = CachePath::manifest();
        $metadataPath = CachePath::metadata();
        $abilityPath = CachePath::abilities();

        expect(is_file($manifestPath))->toBeTrue()
            ->and(is_file($metadataPath))->toBeTrue()
            ->and(is_file($abilityPath))->toBeTrue();

        /** @var array<string, mixed> $manifest */
        $manifest = include $manifestPath;
        /** @var array<string, mixed> $metadata */
        $metadata = include $metadataPath;
        /** @var array<string, mixed> $ability */
        $ability = include $abilityPath;

        expect($manifest['schema_version'])->toBe(1)
            ->and($manifest['modules'])->toHaveKey('platform.build')
            ->and($metadata['schema_version'])->toBe(1)
            ->and($metadata['modules']['platform.build']['controllers']['widget']['access'])->toBe('authorized')
            ->and($metadata['modules']['platform.build']['controllers']['widget'])->toHaveKey('menu')
            ->and($metadata['modules']['platform.build']['controllers']['widget'])->not->toHaveKey('policy')
            ->and($ability['schema_version'])->toBe(1)
            ->and($ability['policies'])->toHaveKey('app.modules.platform.build.policies.widget_policy')
            ->and($ability['gates'][$metadata['modules']['platform.build']['controllers']['widget']['controller']]['index'])
            ->toBe([['app.modules.platform.build.policies.widget_policy.viewAny']]);

        expect(file_get_contents($abilityPath))
            ->not->toContain('denyAsNotFound')
            ->not->toContain('denyWithStatus');

        $catalog = new AbilityCatalogReader($abilityPath);
        $policyKey = 'app.modules.platform.build.policies.widget_policy';

        expect($catalog->keyForClass('App\\Modules\\Platform\\Build\\Models\\Widget'))->toBe($policyKey)
            ->and($catalog->has($policyKey, 'viewAny'))->toBeTrue();

        $registry = new MetadataAbilityRegistry($catalog);

        expect($registry->all())->toHaveKey($policyKey)
            ->and($registry->keyFor('App\\Modules\\Platform\\Build\\Models\\Widget'))->toBe($policyKey);
    });

    it('overwrites an authorizer-generated ability cache with core ownership', function () {
        $abilityPath = CachePath::abilities();
        $directory = dirname($abilityPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($abilityPath, <<<'PHP'
<?php

declare(strict_types=1);

return [
    'schema_version' => 1,
    'compiled_at' => '2026-10-24T00:00:00+00:00',
    'generated_by' => 'laravarc authorizer',
    'policies' => [],
    'gates' => [],
];

PHP);

        expect(Artisan::call('laravarc:cache', ['action' => 'build']))->toBe(0);

        /** @var array<string, mixed> $ability */
        $ability = include $abilityPath;

        expect($ability['generated_by'])->toBe('laravarc core')
            ->and($ability['schema_version'])->toBe(1)
            ->and($ability)->toHaveKey('gates');
    });

    it('fails fast when the ability catalog schema is unknown', function () {
        $path = CachePath::abilities();
        file_put_contents($path, "<?php\n\nreturn ['schema_version' => 99, 'policies' => [], 'gates' => []];\n");

        expect(fn () => (new AbilityCatalogReader($path))->policies())
            ->toThrow(MetadataCompileException::class, 'unsupported schema');
    });

    it('fails fast when the metadata artifact schema is unknown', function () {
        $path = CachePath::metadata();
        file_put_contents($path, "<?php\n\nreturn ['schema_version' => 99, 'modules' => []];\n");

        expect(fn () => (new FileMetadataArtifactStore($path))->read())
            ->toThrow(CorruptMetadataArtifactException::class, 'schema_version');
    });

    it('clears all three cache artifacts', function () {
        Artisan::call('laravarc:cache', ['action' => 'build']);

        expect(Artisan::call('laravarc:cache', ['action' => 'clear']))->toBe(0);

        expect(is_file(CachePath::manifest()))->toBeFalse()
            ->and(is_file(CachePath::metadata()))->toBeFalse()
            ->and(is_file(CachePath::abilities()))->toBeFalse();
    });

    it('skips catalog sync when catalog is not installed', function () {
        expect(Artisan::call('laravarc:cache', ['action' => 'build']))->toBe(0);
    });

    it('syncs catalog rows when installed', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);
        Artisan::call('laravarc:catalog', ['action' => 'install']);

        expect(Artisan::call('laravarc:cache', ['action' => 'build']))->toBe(0);
    });

    it('honours --no-catalog-sync and LARAVARC_CATALOG_SYNC=false', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);
        Artisan::call('laravarc:catalog', ['action' => 'install']);

        putenv('LARAVARC_CATALOG_SYNC=false');
        expect(Artisan::call('laravarc:cache', ['action' => 'build', '--no-catalog-sync' => true]))->toBe(0);
        putenv('LARAVARC_CATALOG_SYNC');
    });

    it('leaves catalog-state.php on clear', function () {
        seedCatalogMetadataModule();
        Artisan::call('laravarc:cache', ['action' => 'build']);
        Artisan::call('laravarc:catalog', ['action' => 'install']);

        Artisan::call('laravarc:cache', ['action' => 'clear']);

        expect(is_file(CachePath::catalogState()))->toBeTrue();
    });
});
