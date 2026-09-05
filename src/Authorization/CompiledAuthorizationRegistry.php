<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use Laravarc\Core\Contracts\MetadataReader;

final class CompiledAuthorizationRegistry
{
    public function __construct(
        private readonly MetadataReader $metadataReader,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function module(string $moduleKey): ?array
    {
        $module = $this->metadataReader->artifact()->modules[$moduleKey] ?? null;

        return is_array($module) ? $module : null;
    }

    /**
     * @return array{model: string|null, policy: string|null, public: bool, methods: array<string, mixed>}|null
     */
    public function controller(string $moduleKey, string $controllerClass): ?array
    {
        $module = $this->module($moduleKey);
        $controllers = is_array($module['policy']['controllers'] ?? null)
            ? $module['policy']['controllers']
            : [];

        $controller = $controllers[$controllerClass] ?? null;

        return is_array($controller) ? $controller : null;
    }

    /**
     * @return list<array{abilities: list<string>, model: string|null}>|null
     */
    public function controllerMethodRequirements(string $controllerClass, string $method): ?array
    {
        foreach ($this->metadataReader->artifact()->modules as $moduleData) {
            if (! is_array($moduleData)) {
                continue;
            }

            $controllers = $moduleData['policy']['controllers'] ?? null;

            if (! is_array($controllers)) {
                continue;
            }

            $controller = $controllers[$controllerClass] ?? null;

            if (! is_array($controller)) {
                continue;
            }

            $requirements = $controller['methods'][$method]['requirements'] ?? null;

            return is_array($requirements) ? $requirements : null;
        }

        return null;
    }

    public function controllerIsPublic(string $moduleKey, string $controllerClass): bool
    {
        $controller = $this->controller($moduleKey, $controllerClass);

        return (bool) ($controller['public'] ?? false);
    }
}
