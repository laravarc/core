<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravarc\Core\Contracts\PolicyEvaluator;

final class GatePolicyEvaluator implements PolicyEvaluator
{
    /** @var list<string> */
    private const CLASS_LEVEL_ABILITIES = ['viewAny', 'create'];

    public function __construct(
        private readonly CompiledAuthorizationRegistry $authorizationRegistry,
        private readonly ControllerModuleResolver $moduleResolver,
        private readonly PolicyTargetResolver $targetResolver,
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
        $module = $moduleKey !== null ? $this->authorizationRegistry->module($moduleKey) : null;

        if ($module === null) {
            throw new AuthorizationException;
        }

        $controllerMeta = $this->authorizationRegistry->controller($moduleKey, $controller::class) ?? [];
        $requirements = $abilityOverride !== null && $abilityOverride !== ''
            ? [['abilities' => [$abilityOverride], 'model' => null]]
            : $this->authorizationRegistry->controllerMethodRequirements($controller::class, $route->getActionMethod());

        if ($requirements === null || $requirements === []) {
            if ($this->shouldAllowWithoutPolicy($controllerMeta, (bool) config('laravarc.require_policy', false))) {
                return;
            }

            throw new AuthorizationException;
        }

        foreach ($requirements as $requirement) {
            if (! $this->passesRequirement($request, $requirement, $module, $controllerMeta)) {
                throw new AuthorizationException;
            }
        }
    }

    /**
     * @param  array{public?: bool}  $controllerMeta
     */
    private function shouldAllowWithoutPolicy(array $controllerMeta, bool $requirePolicy): bool
    {
        if (! $requirePolicy) {
            return true;
        }

        return (bool) ($controllerMeta['public'] ?? false);
    }

    /**
     * @param  array{abilities: list<string>, model: string|null}  $requirement
     * @param  array<string, mixed>  $module
     * @param  array{model?: string|null, policy?: string|null}  $controllerMeta
     */
    private function passesRequirement(Request $request, array $requirement, array $module, array $controllerMeta): bool
    {
        $policyMeta = is_array($module['policy'] ?? null) ? $module['policy'] : [];

        foreach ($requirement['abilities'] as $ability) {
            $override = $policyMeta['ability_overrides'][$ability] ?? null;
            $modelClass = is_array($override) && is_string($override['model'] ?? null)
                ? $override['model']
                : ($requirement['model']
                    ?? ($controllerMeta['model'] ?? null)
                    ?? ($policyMeta['model'] ?? null));

            if (! is_string($modelClass) || $modelClass === '') {
                continue;
            }

            $target = $this->resolveTarget($request, $ability, $modelClass);

            if ($target === null) {
                continue;
            }

            try {
                Gate::authorize($ability, $target);

                return true;
            } catch (AuthorizationException) {
                continue;
            }
        }

        return false;
    }

    private function resolveTarget(Request $request, string $ability, string $modelClass): mixed
    {
        if (in_array($ability, self::CLASS_LEVEL_ABILITIES, true)) {
            return $modelClass;
        }

        return $this->targetResolver->resolve($request, $ability, $modelClass);
    }
}
