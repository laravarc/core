<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Convention\Layer;
use Laravarc\Core\Convention\ResolvedClass;
use Laravarc\Core\Module\ModuleIdentity;

interface LayerResolver
{
    public function resolve(ModuleIdentity $identity, Layer $layer, ?string $name = null): ResolvedClass;
}
