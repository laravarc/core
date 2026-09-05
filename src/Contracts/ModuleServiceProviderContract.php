<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

/**
 * Marker for Arc module primary ServiceProviders.
 *
 * Implement on a class that extends {@see \Illuminate\Support\ServiceProvider}.
 * Do not extend a Laravarc-provided base ServiceProvider — use this interface only.
 */
interface ModuleServiceProviderContract
{
    /**
     * Module path relative to modules_path (forward slashes).
     *
     * Example: Admin/Platform/Catalog
     */
    public static function modulePath(): string;
}
