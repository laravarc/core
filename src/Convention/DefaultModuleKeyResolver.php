<?php

declare(strict_types=1);

namespace Laravarc\Core\Convention;

use Illuminate\Support\Str;
use Laravarc\Core\Contracts\ModuleKeyResolver as ModuleKeyResolverContract;
use Laravarc\Core\Module\ModuleIdentity;

final class DefaultModuleKeyResolver implements ModuleKeyResolverContract
{
    public function resolve(ModuleIdentity $identity): string
    {
        return collect($identity->segments)
            ->map(static fn (string $segment): string => Str::kebab($segment))
            ->implode('.');
    }
}
