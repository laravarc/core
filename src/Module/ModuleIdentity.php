<?php

declare(strict_types=1);

namespace Laravarc\Core\Module;

use Illuminate\Support\Str;

final readonly class ModuleIdentity
{
    /**
     * @param  list<string>  $segments
     */
    public function __construct(
        public string $path,
        public array $segments,
        public string $namespace,
        public string $entityName,
        public string $defaultTableName,
        public string $rootPath,
    ) {}

    public static function fromPath(
        string $path,
        string $modulesPath,
        string $moduleNamespace,
        ?ModulePathValidator $validator = null,
        ?string $rootPathOverride = null,
    ): self {
        $validator ??= new ModulePathValidator;
        $segments = $validator->normalize($path);
        $normalizedPath = implode('/', $segments);
        $rootPath = $rootPathOverride ?? $validator->resolveRootPath($modulesPath, $normalizedPath);

        $namespaceSuffix = collect($segments)
            ->map(static fn (string $segment): string => Str::studly($segment))
            ->implode('\\');

        $namespace = rtrim($moduleNamespace, '\\');
        if ($namespaceSuffix !== '') {
            $namespace .= '\\'.$namespaceSuffix;
        }

        $lastSegment = $segments[array_key_last($segments)];
        $entityName = Str::studly(Str::singular($lastSegment));
        $defaultTableName = Str::snake(Str::plural(Str::studly($lastSegment)));

        return new self(
            path: $normalizedPath,
            segments: $segments,
            namespace: $namespace,
            entityName: $entityName,
            defaultTableName: $defaultTableName,
            rootPath: $rootPath,
        );
    }

    public function existsOnFilesystem(): bool
    {
        return is_dir($this->rootPath);
    }
}
