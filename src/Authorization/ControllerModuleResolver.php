<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use Illuminate\Support\Str;
use Laravarc\Core\Contracts\ModuleKeyResolver;
use Laravarc\Core\Module\ModuleIdentity;

final class ControllerModuleResolver
{
    /** @var list<string> */
    private const LAYER_SEGMENTS = ['Controllers', 'Http'];

    public function __construct(
        private readonly string $modulesPath,
        private readonly string $moduleNamespace,
        private readonly ModuleKeyResolver $moduleKeyResolver,
    ) {}

    public function resolveFromController(object $controller): ?string
    {
        return $this->resolveFromClass($controller::class);
    }

    public function resolveFromClass(string $controllerClass): ?string
    {
        $prefix = rtrim($this->moduleNamespace, '\\').'\\';

        if (! str_starts_with($controllerClass, $prefix)) {
            return null;
        }

        $suffix = substr($controllerClass, strlen($prefix));
        $segments = explode('\\', $suffix);
        $moduleSegments = [];

        foreach ($segments as $segment) {
            if (in_array($segment, self::LAYER_SEGMENTS, true)) {
                break;
            }

            $moduleSegments[] = Str::kebab($segment);
        }

        if ($moduleSegments === []) {
            return null;
        }

        $identity = ModuleIdentity::fromPath(
            path: implode('/', $moduleSegments),
            modulesPath: $this->modulesPath,
            moduleNamespace: $this->moduleNamespace,
        );

        return $this->moduleKeyResolver->resolve($identity);
    }
}
