<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Convention\ResolvedClass;
use Laravarc\Core\Module\ModuleIdentity;

interface RequestResolver
{
    public function resolve(ModuleIdentity $identity, string $action): ResolvedClass;
}
