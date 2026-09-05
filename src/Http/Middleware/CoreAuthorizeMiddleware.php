<?php

declare(strict_types=1);

namespace Laravarc\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravarc\Core\Contracts\PolicyEvaluator;
use Symfony\Component\HttpFoundation\Response;

final class CoreAuthorizeMiddleware
{
    public function __construct(
        private readonly PolicyEvaluator $policyEvaluator,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $this->policyEvaluator->authorize($request, $ability);

        return $next($request);
    }
}
