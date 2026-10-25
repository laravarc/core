<?php

declare(strict_types=1);

function seedCatalogMetadataModule(): void
{
    $modulesPath = (string) config('laravarc.modules_path');
    $moduleRoot = $modulesPath.'/Demo/Catalog';
    $namespace = 'App\\Modules\\Demo\\Catalog';

    mkdir($moduleRoot.'/Controllers', 0777, true);

    file_put_contents($moduleRoot.'/Controllers/DemoController.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Controllers;

use Laravarc\\Core\\Metadata\\Attributes\\Feature;
use Laravarc\\Core\\Metadata\\Attributes\\Menu;

#[Menu(key: 'demo.menu', label: 'Demo Menu', path: '/demo', order: 1)]
final class DemoController
{
    #[Feature(key: 'demo.feature', label: 'Demo Feature', menu: 'demo.menu', placement: 'tab', order: 1)]
    public function index(): string
    {
        return 'ok';
    }
}
PHP);
}
