<?php

declare(strict_types=1);

namespace Laravarc\Core\Module;

use InvalidArgumentException;

final class ModuleServiceProviderPath
{
    /**
     * Derive module path (forward slashes) from a primary ServiceProvider FQCN.
     *
     * Example: App\Modules\Admin\Platform\Catalog\Providers\CatalogServiceProvider → Admin/Platform/Catalog
     */
    public static function forClass(string $providerClass): string
    {
        $moduleNamespace = rtrim((string) config('laravarc.module_namespace', 'App\\Modules'), '\\');

        if (! str_starts_with($providerClass, $moduleNamespace.'\\')) {
            throw new InvalidArgumentException(sprintf(
                'Provider class [%s] must live under module namespace [%s].',
                $providerClass,
                $moduleNamespace,
            ));
        }

        $relative = substr($providerClass, strlen($moduleNamespace) + 1);
        $segments = explode('\\', $relative);

        if (count($segments) < 2) {
            throw new InvalidArgumentException(sprintf(
                'Provider class [%s] has no module path segments.',
                $providerClass,
            ));
        }

        $providersSegment = $segments[count($segments) - 2] ?? '';

        if ($providersSegment !== 'Providers') {
            throw new InvalidArgumentException(sprintf(
                'Provider class [%s] must be declared under a Providers segment.',
                $providerClass,
            ));
        }

        array_pop($segments);
        array_pop($segments);

        if ($segments === []) {
            throw new InvalidArgumentException(sprintf(
                'Provider class [%s] does not resolve to a module path.',
                $providerClass,
            ));
        }

        return implode('/', $segments);
    }
}
