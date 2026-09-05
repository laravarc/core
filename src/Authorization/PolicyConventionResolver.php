<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

final class PolicyConventionResolver
{
    /**
     * @return array{model: string|null, policy: string|null}
     */
    public function resolveModuleDefaults(string $moduleRoot, string $moduleNamespace, string $moduleEntityName): array
    {
        return [
            'model' => $this->resolveClass($moduleRoot, $moduleNamespace, 'Models', $moduleEntityName),
            'policy' => $this->resolveClass($moduleRoot, $moduleNamespace, 'Policies', $moduleEntityName.'Policy'),
        ];
    }

    /**
     * @param  array{model: string|null, policy: string|null}  $moduleDefaults
     * @return array{model: string|null, policy: string|null}
     */
    public function resolveController(
        string $moduleRoot,
        string $moduleNamespace,
        string $controllerClass,
        array $moduleDefaults,
    ): array {
        $resourceName = $this->controllerResourceName($controllerClass);

        return [
            'model' => $this->resolveClass($moduleRoot, $moduleNamespace, 'Models', $resourceName)
                ?? $moduleDefaults['model'],
            'policy' => $this->resolveClass($moduleRoot, $moduleNamespace, 'Policies', $resourceName.'Policy')
                ?? $moduleDefaults['policy'],
        ];
    }

    public function controllerResourceName(string $controllerClass): string
    {
        $shortName = class_basename($controllerClass);

        if (str_ends_with($shortName, 'Controller')) {
            return substr($shortName, 0, -strlen('Controller'));
        }

        return $shortName;
    }

    private function resolveClass(string $moduleRoot, string $moduleNamespace, string $layer, string $shortName): ?string
    {
        $file = $moduleRoot.'/'.$layer.'/'.$shortName.'.php';

        if (! is_file($file)) {
            return null;
        }

        $className = $moduleNamespace.'\\'.$layer.'\\'.$shortName;

        if (! class_exists($className, false)) {
            require_once $file;
        }

        return class_exists($className) ? $className : null;
    }
}
