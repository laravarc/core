<?php

declare(strict_types=1);

use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleLayout;

function createRelocateModuleFixture(string $modulesPath, string $path, array $files = []): string
{
    $moduleRoot = $modulesPath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);

    foreach ([
        ModuleLayout::CONTROLLERS,
        ModuleLayout::MODELS,
        ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS,
        ModuleLayout::ROUTES,
    ] as $directory) {
        mkdir($moduleRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $directory), 0777, true);
    }

    foreach ($files as $relativePath => $contents) {
        $absolutePath = $moduleRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($absolutePath, $contents);
    }

    return $moduleRoot;
}

describe('FqcnNamespaceReplacer', function () {
    it('replaces exact namespace prefixes without touching similar namespaces', function () {
        $replacer = new \Laravarc\Core\Commands\Services\FqcnNamespaceReplacer(app('files'));

        $contents = <<<'PHP'
<?php

namespace App\Modules\Hotel\Booking\Controllers;

use App\Modules\Hotel\Booking\Models\Booking;
use App\Modules\Hotel\BookingHistory\Models\BookingHistory;

class BookingController
{
    public function index(Booking $booking, BookingHistory $history): void {}
}

PHP;

        $updated = $replacer->replaceInContents(
            $contents,
            'App\\Modules\\Hotel\\Booking',
            'App\\Modules\\Booking',
        );

        expect($updated)
            ->toContain('namespace App\\Modules\\Booking\\Controllers;')
            ->toContain('use App\\Modules\\Booking\\Models\\Booking;')
            ->toContain('use App\\Modules\\Hotel\\BookingHistory\\Models\\BookingHistory;')
            ->not->toContain('App\\Modules\\Hotel\\Booking\\Models\\Booking');
    });
});

describe('laravarc:module migrate', function () {
    beforeEach(function () {
        $this->modulesPath = config('laravarc.modules_path');
        $this->artisan('laravarc:cache', ['action' => 'refresh']);
    });

    it('previews relocation without changing files in dry-run mode', function () {
        createRelocateModuleFixture($this->modulesPath, 'Group/Item', [
            'Controllers/ItemController.php' => <<<'PHP'
<?php

namespace App\Modules\Group\Item\Controllers;

class ItemController {}

PHP,
        ]);

        $this->artisan('laravarc:cache', ['action' => 'refresh']);

        $this->artisan('laravarc:module', [
            'action' => 'migrate',
            'path' => 'group/item',
            'target' => 'item',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Files to move:')
            ->expectsOutputToContain('Dry run complete. No files were changed.')
            ->assertExitCode(0);

        expect(is_dir($this->modulesPath.'/Group/Item'))->toBeTrue()
            ->and(is_dir($this->modulesPath.'/Item'))->toBeFalse();
    });

    it('relocates a module and updates namespaces including cross-module references', function () {
        createRelocateModuleFixture($this->modulesPath, 'Hotel/Booking', [
            'Controllers/BookingController.php' => <<<'PHP'
<?php

namespace App\Modules\Hotel\Booking\Controllers;

use App\Modules\Hotel\Booking\Models\Booking;

class BookingController {}

PHP,
            'Models/Booking.php' => <<<'PHP'
<?php

namespace App\Modules\Hotel\Booking\Models;

class Booking {}

PHP,
            'Routes/BookingRoute.php' => <<<'PHP'
<?php

use App\Modules\Hotel\Booking\Controllers\BookingController;

PHP,
        ]);

        createRelocateModuleFixture($this->modulesPath, 'Sales/Report', [
            'Services/ReportService.php' => <<<'PHP'
<?php

namespace App\Modules\Sales\Report\Services;

use App\Modules\Hotel\Booking\Models\Booking;

class ReportService {}

PHP,
        ]);

        $this->artisan('laravarc:cache', ['action' => 'refresh']);

        $this->artisan('laravarc:module', [
            'action' => 'migrate',
            'path' => 'hotel/booking',
            'target' => 'booking',
            '--force' => true,
        ])
            ->expectsOutputToContain('Module relocated from [Hotel/Booking] to [Booking] successfully.')
            ->expectsOutputToContain('Database table names and migration files were NOT changed')
            ->assertExitCode(0);

        expect(is_dir($this->modulesPath.'/Hotel/Booking'))->toBeFalse()
            ->and(is_dir($this->modulesPath.'/Booking'))->toBeTrue()
            ->and(file_get_contents($this->modulesPath.'/Booking/Controllers/BookingController.php'))
            ->toContain('namespace App\\Modules\\Booking\\Controllers;')
            ->and(file_get_contents($this->modulesPath.'/Sales/Report/Services/ReportService.php'))
            ->toContain('use App\\Modules\\Booking\\Models\\Booking;')
            ->and(app(ModuleRegistry::class)->findByPath('Booking'))->not->toBeNull()
            ->and(app(ModuleRegistry::class)->findByPath('Hotel/Booking'))->toBeNull();
    });

    it('fails when the target module path already exists', function () {
        createRelocateModuleFixture($this->modulesPath, 'Catalog/Product');
        createRelocateModuleFixture($this->modulesPath, 'Product');

        $this->artisan('laravarc:cache', ['action' => 'refresh']);

        $this->artisan('laravarc:module', [
            'action' => 'migrate',
            'path' => 'catalog/product',
            'target' => 'product',
            '--force' => true,
        ])
            ->expectsOutputToContain('already exists')
            ->assertExitCode(1);
    });

    it('fails when the source module is not discovered', function () {
        $this->artisan('laravarc:module', [
            'action' => 'migrate',
            'path' => 'missing/module',
            'target' => 'module',
            '--force' => true,
        ])
            ->expectsOutputToContain('Module not found')
            ->assertExitCode(1);
    });

    it('requires a target path for migrate action', function () {
        $this->artisan('laravarc:module', [
            'action' => 'migrate',
            'path' => 'hotel/booking',
        ])
            ->expectsOutputToContain('target module path is required')
            ->assertExitCode(1);
    });
});
