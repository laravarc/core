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

    /**
     * Resolve the policy class for a declared model using the Laravel
     * convention `Models\\X` -> `Policies\\XPolicy` inside the same module.
     *
     * @param  class-string  $modelClass
     * @return class-string|null
     */
    public function resolvePolicyForModel(string $moduleRoot, string $moduleNamespace, string $modelClass): ?string
    {
        $short = class_basename(ltrim($modelClass, '\\'));
        $className = rtrim($moduleNamespace, '\\').'\\Policies\\'.$short.'Policy';

        if (! class_exists($className, false)) {
            $file = $moduleRoot.'/Policies/'.$short.'Policy.php';

            if (is_file($file)) {
                require_once $file;
            }
        }

        return class_exists($className) ? $className : null;
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
