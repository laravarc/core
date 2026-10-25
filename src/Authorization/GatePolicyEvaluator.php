<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Contracts\PolicyEvaluator;

/**
 * Evaluates the generated DNF gates: [[a,b],[c,d]] passes when (a AND b) or
 * (c AND d) are all granted to the authenticated user (super bypasses).
 */
final class GatePolicyEvaluator implements PolicyEvaluator
{
    public function __construct(
        private readonly MetadataReader $metadata,
        private readonly AbilityCatalog $catalog,
        private readonly ControllerModuleResolver $moduleResolver,
    ) {}

    public function authorize(Request $request, ?string $abilityOverride = null): void
    {
        $route = $request->route();

        if ($route === null) {
            throw new AuthorizationException;
        }

        $controller = $route->getController();

        if (! is_object($controller)) {
            throw new AuthorizationException;
        }

        $moduleKey = $this->moduleResolver->resolveFromController($controller);
        $module = $moduleKey !== null ? $this->module($moduleKey) : null;

        if ($module === null) {
            throw new AuthorizationException;
        }

        $slug = $this->controllerSlug($controller::class);
        $meta = is_array($module['controllers'][$slug] ?? null) ? $module['controllers'][$slug] : null;

        if ($meta === null) {
            throw new AuthorizationException;
        }

        $user = $request->user();

        if ($user !== null && method_exists($user, 'isSuper') && $user->isSuper()) {
            return;
        }

        $gate = $abilityOverride !== null && $abilityOverride !== ''
            ? [[$abilityOverride]]
            : $this->catalog->gateFor($controller::class, $route->getActionMethod());

        if ($gate === null || $gate === []) {
            if (($meta['access'] ?? null) === 'authorized') {
                throw new AuthorizationException;
            }

            return;
        }

        foreach ($gate as $group) {
            if ($this->groupPasses($user, $group)) {
                return;
            }
        }

        throw new AuthorizationException;
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
    private function module(string $moduleKey): ?array
    {
        $module = $this->metadata->artifact()->modules[$moduleKey] ?? null;

        return is_array($module) ? $module : null;
    }

    /**
     * @param  list<string>  $abilities
     */
    private function groupPasses(mixed $user, array $abilities): bool
    {
        if ($abilities === [] || ! is_object($user) || ! method_exists($user, 'hasAbility')) {
            return false;
        }

        foreach ($abilities as $ability) {
            if (! $user->hasAbility($ability)) {
                return false;
            }
        }

        return true;
    }

    private function controllerSlug(string $controllerClass): string
    {
        $short = class_basename($controllerClass);

        if (str_ends_with($short, 'Controller')) {
            $short = substr($short, 0, -strlen('Controller'));
        }

        return Str::snake($short);
    }
}
