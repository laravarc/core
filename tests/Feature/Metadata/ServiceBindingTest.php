<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Laravarc\Core\Authorization\CoreServiceRegistrar;
use Laravarc\Core\Module\ModuleLayout;

describe('compiled service bindings', function () {
    it('registers command and query contracts from compiled metadata', function () {
        $modulesPath = config('laravarc.modules_path');
        $moduleRoot = $modulesPath.'/Platform/Binding';
        $namespace = 'App\\Modules\\Platform\\Binding';

        mkdir($moduleRoot.'/'.ModuleLayout::CONTROLLERS, 0777, true);
        mkdir($moduleRoot.'/Services/Commands', 0777, true);
        mkdir($moduleRoot.'/Services/Queries', 0777, true);

        file_put_contents($moduleRoot.'/Controllers/BindingController.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Controllers;

final class BindingController {}
PHP);

        file_put_contents($moduleRoot.'/Services/Commands/BindingCommandService.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Services\\Commands;

use Laravarc\\Core\\Metadata\\Attributes\\CommandContract;

final class BindingCommandService
{
    #[CommandContract]
    public function createBinding(array \$payload): array
    {
        return \$payload;
    }
}
PHP);

        file_put_contents($moduleRoot.'/Services/Queries/BindingQueryService.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Services\\Queries;

use Laravarc\\Core\\Metadata\\Attributes\\QueryContract;

final class BindingQueryService
{
    #[QueryContract]
    public function findBinding(int \$id): ?array
    {
        return ['id' => \$id];
    }
}
PHP);

        expect(Artisan::call('laravarc:cache', ['action' => 'refresh']))->toBe(0)
            ->and(Artisan::call('laravarc:contract', ['action' => 'sync', 'module' => 'platform/binding']))->toBe(0)
            ->and(Artisan::call('laravarc:metadata', ['action' => 'compile', '--module' => 'platform/binding']))->toBe(0);

        $services = app(\Laravarc\Core\Contracts\MetadataReader::class)->artifact()->modules['platform.binding']['services'] ?? [];
        expect($services)->not->toBeEmpty();

        $sharedRoot = config('laravarc.shared_path').'/Platform/Binding/Contracts';
        require_once $sharedRoot.'/BindingCommandServiceContract.php';
        require_once $sharedRoot.'/BindingQueryServiceContract.php';
        require_once $moduleRoot.'/Services/Commands/BindingCommandService.php';
        require_once $moduleRoot.'/Services/Queries/BindingQueryService.php';

        app(CoreServiceRegistrar::class)->register();

        $commandContract = 'App\\Shared\\Platform\\Binding\\Contracts\\BindingCommandServiceContract';
        $queryContract = 'App\\Shared\\Platform\\Binding\\Contracts\\BindingQueryServiceContract';

        expect(app($commandContract))->toBeInstanceOf($namespace.'\\Services\\Commands\\BindingCommandService')
            ->and(app($queryContract))->toBeInstanceOf($namespace.'\\Services\\Queries\\BindingQueryService');
    });
});
