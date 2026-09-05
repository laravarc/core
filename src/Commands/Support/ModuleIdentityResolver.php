<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Support;

use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModulePathValidator;

final class ModuleIdentityResolver
{
    public function __construct(
        private readonly string $modulesPath,
        private readonly string $moduleNamespace,
        private readonly ModulePathValidator $pathValidator = new ModulePathValidator,
    ) {}

    public function resolve(string $path): ModuleIdentity
    {
        return ModuleIdentity::fromPath(
            path: $path,
            modulesPath: $this->modulesPath,
            moduleNamespace: $this->moduleNamespace,
            validator: $this->pathValidator,
        );
    }
}
