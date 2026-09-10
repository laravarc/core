<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as Artisan;
use Laravarc\Core\Commands\Services\ModuleSeeder;
use Laravarc\Core\Commands\Support\ModuleIdentityResolver;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Tests\Fixtures\SeedPriority\ModuleA\Database\Seeders\EarlySeeder;
use Laravarc\Core\Tests\Fixtures\SeedPriority\ModuleZ\Database\Seeders\LateSeeder;
use Laravarc\Core\Tests\Fixtures\SeedPriority\Sort\PlainBSeeder;
use Laravarc\Core\Tests\Fixtures\SeedPriority\Sort\PriorityASeeder;
use Laravarc\Core\Tests\Fixtures\SeedPriority\Sort\PriorityBSeeder;
use Laravarc\Core\Tests\Fixtures\SeedPriority\Sort\PriorityCSeeder;

describe('ModuleSeeder seed priority', function () {
    beforeEach(function () {
        $this->artisan = Mockery::mock(Artisan::class);
        $this->modulesPath = sys_get_temp_dir().'/arc-seed-priority-'.uniqid('', true);
        mkdir($this->modulesPath, 0777, true);

        $this->seeder = new ModuleSeeder(
            identityResolver: new ModuleIdentityResolver(
                modulesPath: $this->modulesPath,
                moduleNamespace: 'App\\Modules',
            ),
            moduleRegistry: app(ModuleRegistry::class),
            artisan: $this->artisan,
            modulesPath: $this->modulesPath,
        );
    });

    afterEach(function () {
        if (is_dir($this->modulesPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->modulesPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->modulesPath);
        }

        Mockery::close();
    });

    it('places a prioritized seeder before an unattributed seeder', function () {
        $ordered = $this->seeder->sortSeederEntries([
            ['modulePath' => 'Sort', 'class' => PlainBSeeder::class],
            ['modulePath' => 'Sort', 'class' => PriorityASeeder::class],
        ]);

        expect(array_column($ordered, 'class'))->toBe([
            PriorityASeeder::class,
            PlainBSeeder::class,
        ]);
    });

    it('sorts attributed seeders by priority descending', function () {
        $ordered = $this->seeder->sortSeederEntries([
            ['modulePath' => 'Sort', 'class' => PriorityBSeeder::class],
            ['modulePath' => 'Sort', 'class' => PriorityASeeder::class],
        ]);

        expect(array_column($ordered, 'class'))->toBe([
            PriorityASeeder::class,
            PriorityBSeeder::class,
        ]);
    });

    it('breaks equal priority ties by FQCN ascending', function () {
        $ordered = $this->seeder->sortSeederEntries([
            ['modulePath' => 'Sort', 'class' => PriorityCSeeder::class],
            ['modulePath' => 'Sort', 'class' => PriorityBSeeder::class],
        ]);

        expect(array_column($ordered, 'class'))->toBe([
            PriorityBSeeder::class,
            PriorityCSeeder::class,
        ]);
    });

    it('flattens across modules so discovery folder order does not change sort', function () {
        $paths = ['ModuleZ', 'ModuleA'];
        $entries = [];

        foreach ($paths as $path) {
            $class = $path === 'ModuleZ' ? LateSeeder::class : EarlySeeder::class;
            $entries[] = ['modulePath' => $path, 'class' => $class];
        }

        expect(array_column($entries, 'modulePath'))->toBe(['ModuleZ', 'ModuleA']);

        $ordered = $this->seeder->sortSeederEntries($entries);

        expect(array_column($ordered, 'class'))->toBe([
            EarlySeeder::class,
            LateSeeder::class,
        ]);

        $reversed = $this->seeder->sortSeederEntries(array_reverse($entries));

        expect(array_column($reversed, 'class'))->toBe(array_column($ordered, 'class'));
    });

    it('dry-runs seeders in final global order across modules', function () {
        $moduleA = $this->modulesPath.'/ModuleA/'.ModuleLayout::DATABASE.'/'.ModuleLayout::SEEDERS;
        $moduleZ = $this->modulesPath.'/ModuleZ/'.ModuleLayout::DATABASE.'/'.ModuleLayout::SEEDERS;
        mkdir($moduleA, 0777, true);
        mkdir($moduleZ, 0777, true);

        file_put_contents($moduleZ.'/LateSeeder.php', <<<'PHP'
<?php
namespace App\Modules\ModuleZ\Database\Seeders;
use Laravarc\Core\Metadata\Attributes\SeedPriority;
use Illuminate\Database\Seeder;
#[SeedPriority(10)]
final class LateSeeder extends Seeder { public function run(): void {} }
PHP);
        file_put_contents($moduleA.'/EarlySeeder.php', <<<'PHP'
<?php
namespace App\Modules\ModuleA\Database\Seeders;
use Laravarc\Core\Metadata\Attributes\SeedPriority;
use Illuminate\Database\Seeder;
#[SeedPriority(100)]
final class EarlySeeder extends Seeder { public function run(): void {} }
PHP);

        $this->artisan->shouldNotReceive('call');

        $results = $this->seeder->seed(modulePath: null, force: false, dryRun: true);

        expect(array_map(static fn ($r) => $r->seederClass, $results))->toBe([
            'App\\Modules\\ModuleA\\Database\\Seeders\\EarlySeeder',
            'App\\Modules\\ModuleZ\\Database\\Seeders\\LateSeeder',
        ]);
    });
});
