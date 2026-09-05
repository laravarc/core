<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Laravarc\Core\Commands\Generation\GenerationSummaryLine;
use Laravarc\Core\Commands\Generation\GenerationSummaryPrinter;
use Laravarc\Core\Commands\Support\ModuleGenerationState;
use Laravarc\Core\Contracts\SchemaReader;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Schema\ColumnTypeMapper;
use Laravarc\Core\Schema\DatabaseSchemaReader;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospector;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospectorFactory;

describe('laravarc:module make', function () {
    it('creates migration-only module when table is absent', function () {
        $exitCode = Artisan::call('laravarc:module', [
            'action' => 'make',
            'path' => 'admin/user',
        ]);

        expect($exitCode)->toBe(0, Artisan::output());

        $modulesPath = config('laravarc.modules_path');
        $migrationDir = $modulesPath.'/Admin/User/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS;

        expect(glob($migrationDir.'/*.php'))->not->toBeEmpty()
            ->and(is_dir($modulesPath.'/Admin/User/Database/Seeders'))->toBeTrue()
            ->and(is_dir($modulesPath.'/Admin/User/Models'))->toBeFalse();
    });

    it('fails when module already exists without refresh', function () {
        Artisan::call('laravarc:module', ['action' => 'make', 'path' => 'admin/user']);

        $exitCode = Artisan::call('laravarc:module', [
            'action' => 'make',
            'path' => 'admin/user',
        ]);

        expect($exitCode)->toBe(1);
    });

    it('supports dry-run without writing files', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/admin/product';

        $exitCode = Artisan::call('laravarc:module', [
            'action' => 'make',
            'path' => 'admin/product',
            '--dry-run' => true,
        ]);

        expect($exitCode)->toBe(0)
            ->and(is_dir($moduleRoot))->toBeFalse();
    });

    it('generates service contracts when --contract is passed', function () {
        app()->instance(SchemaReader::class, new DatabaseSchemaReader(
            introspectorFactory: new FakeSchemaIntrospectorFactory(new FakeSchemaIntrospector(
                columns: [
                    'users' => [
                        [
                            'name' => 'id',
                            'type_name' => 'integer',
                            'type' => 'integer',
                            'nullable' => false,
                            'default' => null,
                            'auto_increment' => true,
                        ],
                        [
                            'name' => 'email',
                            'type_name' => 'varchar',
                            'type' => 'varchar(255)',
                            'nullable' => false,
                            'default' => null,
                            'auto_increment' => false,
                        ],
                    ],
                ],
                indexes: [
                    'users' => [
                        ['columns' => ['id'], 'primary' => true, 'unique' => true],
                    ],
                ],
            )),
            columnTypeMapper: new ColumnTypeMapper,
        ));

        $path = 'platform/maker'.bin2hex(random_bytes(3));
        $exitCode = Artisan::call('laravarc:module', [
            'action' => 'make',
            'path' => $path,
            '--table' => 'users',
            '--contract' => true,
        ]);

        $identity = ModuleIdentity::fromPath($path, config('laravarc.modules_path'), 'App\\Modules');
        $contractRoot = config('laravarc.shared_path').'/'.$identity->path.'/Contracts';

        expect($exitCode)->toBe(0, Artisan::output())
            ->and(is_file($contractRoot.'/'.$identity->entityName.'CommandServiceContract.php'))->toBeTrue()
            ->and(is_file($contractRoot.'/'.$identity->entityName.'QueryServiceContract.php'))->toBeTrue();
    });

    it('generates service provider and core extension when --with-extension is passed', function () {
        $path = 'platform/extension'.bin2hex(random_bytes(3));
        $exitCode = Artisan::call('laravarc:module', [
            'action' => 'make',
            'path' => $path,
            '--with-extension' => true,
        ]);

        $identity = ModuleIdentity::fromPath($path, config('laravarc.modules_path'), 'App\\Modules');
        $providerPath = $identity->rootPath.'/Providers/'.$identity->entityName.'ServiceProvider.php';
        $extensionPath = $identity->rootPath.'/Extensions/'.$identity->entityName.'CoreExtension.php';

        expect($exitCode)->toBe(0, Artisan::output())
            ->and(is_file($providerPath))->toBeTrue()
            ->and(is_file($extensionPath))->toBeTrue()
            ->and(file_get_contents($providerPath))->toContain("config()->push('laravarc.extensions', {$identity->entityName}CoreExtension::class)")
            ->and(file_get_contents($extensionPath))->toContain('implements CoreExtension');
    });
});

describe('laravarc:module remove', function () {
    it('removes an existing module directory', function () {
        Artisan::call('laravarc:module', ['action' => 'make', 'path' => 'admin/user']);

        $modulesPath = config('laravarc.modules_path');

        expect(is_dir($modulesPath.'/Admin/User'))->toBeTrue();

        $exitCode = Artisan::call('laravarc:module', [
            'action' => 'remove',
            'path' => 'admin/user',
            '--force' => true,
        ]);

        expect($exitCode)->toBe(0)
            ->and(is_dir($modulesPath.'/Admin/User'))->toBeFalse();
    });
});

describe('laravarc:cache commands', function () {
    it('refreshes module manifest cache', function () {
        Artisan::call('laravarc:module', ['action' => 'make', 'path' => 'admin/user']);

        $exitCode = Artisan::call('laravarc:cache', ['action' => 'refresh']);

        expect($exitCode)->toBe(0, Artisan::output());
    });

    it('clears module manifest cache', function () {
        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        $exitCode = Artisan::call('laravarc:cache', ['action' => 'clear']);

        expect($exitCode)->toBe(0, Artisan::output());
    });
});

describe('laravarc:metadata compile', function () {
    it('compiles metadata artifact from manifest', function () {
        $path = 'inventory/widget'.bin2hex(random_bytes(4));
        Artisan::call('laravarc:module', ['action' => 'make', 'path' => $path]);
        $identity = ModuleIdentity::fromPath($path, config('laravarc.modules_path'), 'App\\Modules');
        seedMetadataPolicyFixture($identity);
        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        $result = app(\Laravarc\Core\Contracts\MetadataCompiler::class)->compile();

        expect($result->moduleCount)->toBeGreaterThan(0)
            ->and($result->persisted)->toBeTrue()
            ->and(file_exists(config('laravarc.metadata_file_path')))->toBeTrue()
            ->and($result->artifact->modules)->toBeArray();
    });

    it('supports scoped compile for a single module', function () {
        $path = 'inventory/widget'.bin2hex(random_bytes(4));
        Artisan::call('laravarc:module', ['action' => 'make', 'path' => $path]);
        $identity = ModuleIdentity::fromPath($path, config('laravarc.modules_path'), 'App\\Modules');
        seedMetadataPolicyFixture($identity);
        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        $exitCode = Artisan::call('laravarc:metadata', ['action' => 'compile', '--module' => $path]);

        expect($exitCode)->toBe(0, Artisan::output());
    });

    it('runs through the artisan command', function () {
        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        $exitCode = Artisan::call('laravarc:metadata', ['action' => 'compile', '--dry-run' => true]);

        expect($exitCode)->toBe(0, Artisan::output());
    });
});

describe('ModuleGenerationState', function () {
    it('detects migration-only modules that still need generation', function () {
        $modulesPath = config('laravarc.modules_path');
        mkdir($modulesPath.'/Admin/User/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS, 0777, true);
        file_put_contents(
            $modulesPath.'/Admin/User/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS.'/2024_01_01_create_users_table.php',
            '<?php',
        );

        $identity = ModuleIdentity::fromPath('admin/user', $modulesPath, 'App\\Modules');
        $state = app(ModuleGenerationState::class);

        expect($state->needsFullGeneration($identity))->toBeTrue();
    });
});

describe('GenerationSummaryPrinter', function () {
    it('formats generated skipped and failed counts', function () {
        $printer = new GenerationSummaryPrinter;
        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        );

        $printer->print($style, 'admin/user', [
            new GenerationSummaryLine('generated', 'Model'),
            new GenerationSummaryLine('skipped', 'Resource', 'stack=blade'),
        ]);

        $text = $output->fetch();

        expect($text)->toContain('Module: admin/user')
            ->toContain('Model generated')
            ->toContain('Resource skipped (stack=blade)')
            ->toContain('Generated: 1')
            ->toContain('Skipped : 1')
            ->toContain('Failed  : 0');
    });
});

function seedMetadataPolicyFixture(ModuleIdentity $identity): void
{
    $moduleRoot = $identity->rootPath;
    $moduleNamespace = $identity->namespace;
    $entityName = $identity->entityName;

    if (! is_dir($moduleRoot)) {
        mkdir($moduleRoot, 0777, true);
    }

    mkdir($moduleRoot.'/Policies', 0777, true);
    mkdir($moduleRoot.'/Controllers', 0777, true);
    mkdir($moduleRoot.'/Models', 0777, true);

    file_put_contents($moduleRoot.'/Models/'.$entityName.'.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$moduleNamespace}\\Models;

final class {$entityName} {}
PHP);

    file_put_contents($moduleRoot.'/Policies/'.$entityName.'Policy.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$moduleNamespace}\\Policies;

final class {$entityName}Policy
{
    public function viewAny(?object \$user): bool
    {
        return true;
    }
}
PHP);

    file_put_contents($moduleRoot.'/Controllers/'.$entityName.'Controller.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$moduleNamespace}\\Controllers;

use Laravarc\\Core\\Metadata\\Attributes\\Policy;

final class {$entityName}Controller
{
    #[Policy(ability: 'viewAny')]
    public function index(): void {}
}
PHP);
}
