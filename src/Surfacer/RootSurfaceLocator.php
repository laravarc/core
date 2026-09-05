<?php

declare(strict_types=1);

namespace Laravarc\Core\Surfacer;

use Illuminate\Support\Str;
use Laravarc\Core\Support\ModuleMetaDirectory;

/**
 * Convention: root module folder X maps to {modules}/{X}/.laravarc/*_surface.php.
 */
final class RootSurfaceLocator
{
    public const SURFACE_FILE_SUFFIX = '_surface.php';

    /**
     * Absolute path of the root module directory on disk, or null if missing.
     * Prefers existing StudlyCase folder, then lowercase.
     */
    public function resolveRootDirectory(string $modulesPath, string $rootSegment): ?string
    {
        $studly = Str::studly($rootSegment);
        $lower = Str::lower($studly);

        $studlyPath = rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$studly;
        if (is_dir($studlyPath)) {
            return $studlyPath;
        }

        $lowerPath = rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$lower;
        if (is_dir($lowerPath)) {
            return $lowerPath;
        }

        return null;
    }

    /**
     * First path segment of a module path (Admin/User → Admin).
     */
    public function rootSegmentFromModulePath(string $modulePath): string
    {
        $normalized = str_replace('\\', '/', trim($modulePath, '/'));
        $parts = explode('/', $normalized);

        return $parts[0] ?? $normalized;
    }

    /**
     * Sorted list of *_surface.php files under the root module's .laravarc dir.
     *
     * @return list<string>
     */
    public function surfaceFilesForRoot(string $modulesPath, string $rootSegment): array
    {
        $rootDir = $this->resolveRootDirectory($modulesPath, $rootSegment);
        if ($rootDir === null) {
            return [];
        }

        $metaDir = $rootDir.DIRECTORY_SEPARATOR.ModuleMetaDirectory::NAME;
        if (! is_dir($metaDir)) {
            return [];
        }

        $pattern = $metaDir.DIRECTORY_SEPARATOR.'*'.self::SURFACE_FILE_SUFFIX;
        $files = glob($pattern) ?: [];

        $result = [];
        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }
            if (! str_ends_with(basename($file), self::SURFACE_FILE_SUFFIX)) {
                continue;
            }
            $result[] = $file;
        }

        sort($result);

        return $result;
    }

    public function hasSurface(string $modulesPath, string $rootSegment): bool
    {
        return $this->surfaceFilesForRoot($modulesPath, $rootSegment) !== [];
    }

    /**
     * Primary surface definition file for a root (first sorted match), or null.
     */
    public function primarySurfaceFile(string $modulesPath, string $rootSegment): ?string
    {
        $files = $this->surfaceFilesForRoot($modulesPath, $rootSegment);

        return $files[0] ?? null;
    }

    /**
     * Read the Surface name from a definition file without relying on a second require.
     */
    public function readSurfaceName(string $definitionFile): ?string
    {
        $real = realpath($definitionFile);
        $surfaceDefinitionClass = 'Laravarc\\Surfacer\\Definition\\SurfaceDefinition';

        if (
            $real !== false
            && class_exists($surfaceDefinitionClass)
            && ! in_array($real, get_included_files(), true)
        ) {
            /** @var mixed $result */
            $result = require $definitionFile;
            if (is_object($result) && is_a($result, $surfaceDefinitionClass)) {
                /** @var object{resolve(): object{name: string}} $result */
                return $result->resolve()->name;
            }
        }

        $contents = (string) file_get_contents($definitionFile);

        if (preg_match(
            '/(?:Surfacer::define|new\s+\\\\?SurfaceDefinition|new\s+\\\\?Laravarc\\\\Surfacer\\\\Definition\\\\SurfaceDefinition)\(\s*[\'"]([^\'"]+)[\'"]/',
            $contents,
            $matches,
        ) === 1) {
            return $matches[1];
        }

        if (preg_match('/Surfacer::define\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/new\s+SurfaceDefinition\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function surfaceNameForRoot(string $modulesPath, string $rootSegment): ?string
    {
        $file = $this->primarySurfaceFile($modulesPath, $rootSegment);
        if ($file === null) {
            return null;
        }

        return $this->readSurfaceName($file);
    }
}
