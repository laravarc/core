<?php

declare(strict_types=1);

namespace Laravarc\Core\Module;

use Illuminate\Support\Str;
use Laravarc\Core\Module\Exceptions\InvalidModulePathException;

final class ModulePathValidator
{
    /**
     * @return list<string>
     */
    public function normalize(string $path): array
    {
        $path = trim($path, '/');

        if ($path === '') {
            throw new InvalidModulePathException('Module path must not be empty.');
        }

        if (str_contains($path, '..')) {
            throw new InvalidModulePathException('Module path must not contain path traversal segments.');
        }

        if (preg_match('/[\x00-\x1F\x7F<>:"|?*\\\\]/', $path) === 1) {
            throw new InvalidModulePathException('Module path contains invalid filesystem characters.');
        }

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            throw new InvalidModulePathException('Module path must not be empty.');
        }

        return array_map(function (string $segment): string {
            $this->validateSegment($segment);

            return Str::studly($segment);
        }, $segments);
    }

    public function resolveRootPath(string $modulesPath, string $path): string
    {
        $segments = $this->normalize($path);
        $modulesRoot = rtrim($modulesPath, DIRECTORY_SEPARATOR);
        $moduleRoot = $modulesRoot.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);

        $resolvedModulesRoot = realpath($modulesRoot);
        $resolvedModuleRoot = realpath(dirname($moduleRoot));

        if ($resolvedModulesRoot === false) {
            return $moduleRoot;
        }

        if ($resolvedModuleRoot !== false) {
            $resolvedModuleRoot = $resolvedModuleRoot.DIRECTORY_SEPARATOR.basename($moduleRoot);
        } else {
            $resolvedModuleRoot = $moduleRoot;
        }

        $normalizedModulesRoot = rtrim(str_replace('\\', '/', $resolvedModulesRoot), '/');
        $normalizedModuleRoot = rtrim(str_replace('\\', '/', $resolvedModuleRoot), '/');

        if ($normalizedModuleRoot !== $normalizedModulesRoot
            && ! str_starts_with($normalizedModuleRoot.'/', $normalizedModulesRoot.'/')) {
            throw new InvalidModulePathException('Module path must not resolve outside the modules root.');
        }

        return $moduleRoot;
    }

    private function validateSegment(string $segment): void
    {
        if ($segment === '.' || $segment === '..') {
            throw new InvalidModulePathException('Module path must not contain path traversal segments.');
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $segment) !== 1) {
            throw new InvalidModulePathException(sprintf(
                'Module path segment "%s" is not a valid namespace segment.',
                $segment,
            ));
        }

        foreach (ModuleLayout::reservedPathSegments() as $reserved) {
            if (strcasecmp($segment, $reserved) === 0) {
                throw new InvalidModulePathException(sprintf(
                    'Module path segment "%s" is reserved and cannot be used.',
                    $segment,
                ));
            }
        }
    }
}
