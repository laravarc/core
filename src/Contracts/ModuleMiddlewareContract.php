<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

/**
 * Marker for Arc module middleware classes under `{Module}/Middlewares/`.
 *
 * Implement on a final class. Do not extend a Laravarc-provided base middleware —
 * use this interface only. Alias is registered via ModuleMiddlewareLoader.
 */
interface ModuleMiddlewareContract
{
    /**
     * Laravel middleware alias (e.g. fiscal_year.context).
     */
    public static function alias(): string;
}
