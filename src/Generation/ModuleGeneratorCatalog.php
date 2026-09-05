<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

use Laravarc\Core\Contracts\ModuleGenerator;
use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Generation\Generators\ControllerGenerator;
use Laravarc\Core\Generation\Generators\EventGenerator;
use Laravarc\Core\Generation\Generators\FormRequestGenerator;
use Laravarc\Core\Generation\Generators\LangGenerator;
use Laravarc\Core\Generation\Generators\ListenerGenerator;
use Laravarc\Core\Generation\Generators\MigrationGenerator;
use Laravarc\Core\Generation\Generators\ModelGenerator;
use Laravarc\Core\Generation\Generators\PolicyGenerator;
use Laravarc\Core\Generation\Generators\RepositoryGenerator;
use Laravarc\Core\Generation\Generators\ResourceGenerator;
use Laravarc\Core\Generation\Generators\RouteGenerator;
use Laravarc\Core\Generation\Generators\SeederGenerator;
use Laravarc\Core\Generation\Generators\ServiceGenerator;
use Laravarc\Core\Generation\Generators\ServiceProviderGenerator;
use Laravarc\Core\Generation\Generators\CoreExtensionGenerator;
use Laravarc\Core\Generation\Generators\ViewsFolderGenerator;

final class ModuleGeneratorCatalog
{
    /**
     * @return list<ModuleGenerator>
     */
    public static function builtIn(?ExtensionManager $extensions = null): array
    {
        return [
            new MigrationGenerator,
            new ModelGenerator,
            new RepositoryGenerator,
            new ServiceGenerator($extensions),
            new FormRequestGenerator,
            new ControllerGenerator,
            new PolicyGenerator,
            new ResourceGenerator,
            new ViewsFolderGenerator,
            new RouteGenerator,
            new EventGenerator,
            new ListenerGenerator,
            new SeederGenerator,
            new LangGenerator,
            new ServiceProviderGenerator,
            new CoreExtensionGenerator,
        ];
    }
}
