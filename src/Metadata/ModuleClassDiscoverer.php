<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Illuminate\Support\Str;

final class ModuleClassDiscoverer
{
    /**
     * @return list<class-string>
     */
    public function discover(string $moduleRoot, string $moduleNamespace): array
    {
        if (! is_dir($moduleRoot)) {
            return [];
        }

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($moduleRoot, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($moduleRoot) + 1));
            $className = $this->pathToClassName($moduleNamespace, $relativePath);

            if ($className === null) {
                continue;
            }

            // Prefer Composer autoload when the class is already known — avoids
            // "Cannot redeclare class" when the same file is visible via two
            // realpaths (e.g. host vs container bind-mount paths in a stale manifest).
            if (! class_exists($className, true)) {
                require_once $file->getPathname();
            }

            if (class_exists($className, false)) {
                $classes[] = $className;
            }
        }

        sort($classes);

        return array_values(array_unique($classes));
    }

    private function pathToClassName(string $moduleNamespace, string $relativePath): ?string
    {
        if (! str_ends_with($relativePath, '.php')) {
            return null;
        }

        $withoutExtension = substr($relativePath, 0, -4);
        $segments = explode('/', $withoutExtension);

        if ($segments === []) {
            return null;
        }

        $classShortName = array_pop($segments);
        $namespaceSuffix = collect($segments)
            ->map(static fn (string $segment): string => Str::studly($segment))
            ->implode('\\');

        $namespace = rtrim($moduleNamespace, '\\');

        if ($namespaceSuffix !== '') {
            $namespace .= '\\'.$namespaceSuffix;
        }

        return $namespace.'\\'.$classShortName;
    }
}
