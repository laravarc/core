<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Module\ModuleIdentity;

interface ModuleKeyResolver
{
    public function resolve(ModuleIdentity $identity): string;
}
