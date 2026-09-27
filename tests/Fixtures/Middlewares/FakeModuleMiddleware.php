<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Laravarc\Core\Contracts\ModuleMiddlewareContract;

/**
 * Example module middleware — satisfies {@see ModuleMiddlewareContract}.
 */
final class FakeModuleMiddleware implements ModuleMiddlewareContract
{
    public static function alias(): string
    {
        return 'fixture.middleware';
    }

    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}
