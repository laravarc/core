<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Laravarc\Core\Module\ModuleLayout;

describe('laravarc:contract sync', function () {
    it('generates contracts from command/query attributes for a module', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/Admin/User';
        $namespace = 'App\\Modules\\Admin\\User';

        mkdir($moduleRoot.'/'.ModuleLayout::CONTROLLERS, 0777, true);
        mkdir($moduleRoot.'/Services/Commands', 0777, true);
        mkdir($moduleRoot.'/Services/Queries', 0777, true);

        file_put_contents($moduleRoot.'/Services/Commands/UserCommandService.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Services\\Commands;

use Laravarc\\Core\\Metadata\\Attributes\\CommandContract;

final class UserCommandService
{
    #[CommandContract]
    public function createUser(array \$payload): array
    {
        return \$payload;
    }
}
PHP);

        file_put_contents($moduleRoot.'/Services/Queries/UserQueryService.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Services\\Queries;

use Laravarc\\Core\\Metadata\\Attributes\\QueryContract;

final class UserQueryService
{
    #[QueryContract]
    public function findUser(int \$id): ?array
    {
        return ['id' => \$id];
    }
}
PHP);

        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        $exitCode = Artisan::call('laravarc:contract', [
            'action' => 'sync',
            'module' => 'admin/user',
        ]);

        $sharedRoot = config('laravarc.shared_path').'/Admin/User/Contracts';
        $commandContract = $sharedRoot.'/UserCommandServiceContract.php';
        $queryContract = $sharedRoot.'/UserQueryServiceContract.php';

        expect($exitCode)->toBe(0, Artisan::output())
            ->and(is_file($commandContract))->toBeTrue()
            ->and(is_file($queryContract))->toBeTrue()
            ->and(file_get_contents($commandContract))->toContain('interface UserCommandServiceContract')
            ->and(file_get_contents($commandContract))->toContain('public function createUser(array $payload): array;')
            ->and(file_get_contents($queryContract))->toContain('interface UserQueryServiceContract')
            ->and(file_get_contents($queryContract))->toContain('public function findUser(int $id): ?array;');
    });

    it('skips renamed contracts at the default managed path', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/Admin/Renamed';
        $namespace = 'App\\Modules\\Admin\\Renamed';
        $sharedRoot = config('laravarc.shared_path').'/Admin/Renamed/Contracts';

        mkdir($moduleRoot.'/'.ModuleLayout::CONTROLLERS, 0777, true);
        mkdir($moduleRoot.'/Services/Commands', 0777, true);
        if (! is_dir($sharedRoot)) {
            mkdir($sharedRoot, 0777, true);
        }

        file_put_contents($moduleRoot.'/Services/Commands/RenamedCommandService.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Services\\Commands;

use Laravarc\\Core\\Metadata\\Attributes\\CommandContract;

final class RenamedCommandService
{
    #[CommandContract]
    public function createRenamed(array \$payload): array
    {
        return \$payload;
    }
}
PHP);

        file_put_contents($sharedRoot.'/RenamedCommandServiceContract.php', <<<PHP
<?php

declare(strict_types=1);

namespace App\\Shared\\Admin\\Renamed\\Contracts;

interface RenamedCommandContract
{
    public function createRenamed(array \$payload): array;
}
PHP);

        Artisan::call('laravarc:cache', ['action' => 'refresh']);
        Artisan::call('laravarc:contract', ['action' => 'sync', 'module' => 'admin/renamed']);

        expect(file_get_contents($sharedRoot.'/RenamedCommandServiceContract.php'))
            ->toContain('interface RenamedCommandContract')
            ->not->toContain('interface RenamedCommandServiceContract');
    });

    it('skips contracts that contain methods missing from the service', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/Admin/Drift';
        $namespace = 'App\\Modules\\Admin\\Drift';
        $sharedRoot = config('laravarc.shared_path').'/Admin/Drift/Contracts';

        mkdir($moduleRoot.'/'.ModuleLayout::CONTROLLERS, 0777, true);
        mkdir($moduleRoot.'/Services/Commands', 0777, true);
        if (! is_dir($sharedRoot)) {
            mkdir($sharedRoot, 0777, true);
        }

        file_put_contents($moduleRoot.'/Services/Commands/DriftCommandService.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Services\\Commands;

use Laravarc\\Core\\Metadata\\Attributes\\CommandContract;

final class DriftCommandService
{
    #[CommandContract]
    public function createDrift(array \$payload): array
    {
        return \$payload;
    }
}
PHP);

        file_put_contents($sharedRoot.'/DriftCommandServiceContract.php', <<<PHP
<?php

declare(strict_types=1);

namespace App\\Shared\\Admin\\Drift\\Contracts;

interface DriftCommandServiceContract
{
    public function createDrift(array \$payload): array;

    public function archiveDrift(int \$id): void;
}
PHP);

        Artisan::call('laravarc:cache', ['action' => 'refresh']);
        Artisan::call('laravarc:contract', ['action' => 'sync', 'module' => 'admin/drift']);

        expect(file_get_contents($sharedRoot.'/DriftCommandServiceContract.php'))
            ->toContain('public function archiveDrift(int $id): void;');
    });

    it('emits use imports for class return types on first generate', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/Admin/Importgen';
        $namespace = 'App\\Modules\\Admin\\Importgen';

        mkdir($moduleRoot.'/'.ModuleLayout::CONTROLLERS, 0777, true);
        mkdir($moduleRoot.'/Services/Queries', 0777, true);

        file_put_contents($moduleRoot.'/Services/Queries/ImportgenQueryService.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Services\\Queries;

use Illuminate\\Support\\Collection;
use Laravarc\\Core\\Metadata\\Attributes\\QueryContract;

final class ImportgenQueryService
{
    #[QueryContract]
    public function listImportgen(string \$locale): Collection
    {
        return collect();
    }
}
PHP);

        Artisan::call('laravarc:cache', ['action' => 'refresh']);

        $exitCode = Artisan::call('laravarc:contract', [
            'action' => 'sync',
            'module' => 'admin/importgen',
        ]);

        $queryContract = config('laravarc.shared_path').'/Admin/Importgen/Contracts/ImportgenQueryServiceContract.php';
        $contents = file_get_contents($queryContract);

        expect($exitCode)->toBe(0, Artisan::output())
            ->and($contents)->toContain('use Illuminate\\Support\\Collection;')
            ->and($contents)->toContain('public function listImportgen(string $locale): Collection;')
            ->and($contents)->not->toContain(': Illuminate\\Support\\Collection')
            ->and($contents)->not->toContain('\\Illuminate\\Support\\Collection');
    });

    it('preserves existing use imports and method PHPDoc when syncing', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/Admin/Keepdoc';
        $namespace = 'App\\Modules\\Admin\\Keepdoc';
        $sharedRoot = config('laravarc.shared_path').'/Admin/Keepdoc/Contracts';

        mkdir($moduleRoot.'/'.ModuleLayout::CONTROLLERS, 0777, true);
        mkdir($moduleRoot.'/Services/Queries', 0777, true);
        mkdir($sharedRoot, 0777, true);

        file_put_contents($moduleRoot.'/Services/Queries/KeepdocQueryService.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Services\\Queries;

use Illuminate\\Support\\Collection;
use Laravarc\\Core\\Metadata\\Attributes\\QueryContract;

final class KeepdocQueryService
{
    /**
     * Service-side doc that must not replace the contract PHPDoc.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[QueryContract]
    public function listKeepdoc(string \$locale): Collection
    {
        return collect();
    }

    #[QueryContract]
    public function findKeepdoc(int \$id): ?array
    {
        return ['id' => \$id];
    }
}
PHP);

        file_put_contents($sharedRoot.'/KeepdocQueryServiceContract.php', <<<PHP
<?php

declare(strict_types=1);

namespace App\\Shared\\Admin\\Keepdoc\\Contracts;

use Illuminate\\Support\\Collection;

interface KeepdocQueryServiceContract
{
    /**
     * @param  list<int>  \$unused
     * @return Collection<int, \\App\\Shared\\Admin\\Keepdoc\\Results\\KeepdocResult>
     */
    public function listKeepdoc(string \$locale): Collection;

    /**
     * Existing find PHPDoc must stay on findKeepdoc only.
     */
    public function findKeepdoc(int \$id): ?array;
}
PHP);

        Artisan::call('laravarc:cache', ['action' => 'refresh']);
        Artisan::call('laravarc:contract', ['action' => 'sync', 'module' => 'admin/keepdoc']);

        $contents = file_get_contents($sharedRoot.'/KeepdocQueryServiceContract.php');

        expect($contents)->toContain('use Illuminate\\Support\\Collection;')
            ->and($contents)->toContain('@return Collection<int, \\App\\Shared\\Admin\\Keepdoc\\Results\\KeepdocResult>')
            ->and($contents)->toContain('@param  list<int>  $unused')
            ->and($contents)->toContain('Existing find PHPDoc must stay on findKeepdoc only.')
            ->and($contents)->not->toContain('Service-side doc that must not replace the contract PHPDoc')
            ->and($contents)->toContain('public function listKeepdoc(string $locale): Collection;')
            ->and($contents)->toContain('public function findKeepdoc(int $id): ?array;')
            ->and(substr_count($contents, 'public function listKeepdoc('))->toBe(1)
            ->and(substr_count($contents, 'public function findKeepdoc('))->toBe(1)
            ->and($contents)->not->toContain('\\Illuminate\\Support\\Collection');
    });
});
