<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Illuminate\Filesystem\Filesystem;
use Laravarc\Core\Commands\Exceptions\ModuleRelocateException;
use Laravarc\Core\Contracts\MetadataCompiler;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Commands\Support\ModuleIdentityResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModuleRelocator
{
    public function __construct(
        private readonly ModuleIdentityResolver $identityResolver,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly FqcnNamespaceReplacer $namespaceReplacer,
        private readonly MetadataCompiler $metadataCompiler,
        private readonly Filesystem $filesystem,
        private readonly string $modulesPath,
    ) {}

    public function plan(string $sourcePath, string $targetPath): ModuleRelocatePlan
    {
        $source = $this->identityResolver->resolve($sourcePath);
        $target = $this->identityResolver->resolve($targetPath);

        $this->assertCanRelocate($source, $target);

        $filesToMove = $this->listFiles($source->rootPath);
        $searchRoots = $this->searchRoots();
        $replacementFiles = $this->namespaceReplacer->findFilesContainingNamespace($searchRoots, $source->namespace);

        $normalizedSourceRoot = $this->normalizePath($source->rootPath);
        $internal = [];
        $crossModule = [];

        foreach ($replacementFiles as $file) {
            if (str_starts_with($this->normalizePath($file), $normalizedSourceRoot.'/')) {
                $internal[] = $file;

                continue;
            }

            $crossModule[] = $file;
        }

        [$routeRenameFrom, $routeRenameTo] = $this->routeFileRename($source, $target);

        return new ModuleRelocatePlan(
            source: $source,
            target: $target,
            filesToMove: $filesToMove,
            internalReplacementFiles: $internal,
            crossModuleReplacementFiles: $crossModule,
            routeFileRenameFrom: $routeRenameFrom,
            routeFileRenameTo: $routeRenameTo,
        );
    }

    public function execute(ModuleRelocatePlan $plan): void
    {
        $this->assertCanRelocate($plan->source, $plan->target);

        foreach ($plan->internalReplacementFiles as $file) {
            if ($this->filesystem->exists($file)) {
                $this->namespaceReplacer->replaceInFile($file, $plan->oldNamespace(), $plan->newNamespace());
            }
        }

        foreach ($plan->crossModuleReplacementFiles as $file) {
            if ($this->filesystem->exists($file)) {
                $this->namespaceReplacer->replaceInFile($file, $plan->oldNamespace(), $plan->newNamespace());
            }
        }

        $this->filesystem->ensureDirectoryExists(dirname($plan->target->rootPath));

        if (! $this->filesystem->moveDirectory($plan->source->rootPath, $plan->target->rootPath)) {
            throw new ModuleRelocateException(sprintf(
                'Failed to move module directory from [%s] to [%s].',
                $plan->source->rootPath,
                $plan->target->rootPath,
            ));
        }

        if ($plan->routeFileRenameFrom !== null && $plan->routeFileRenameTo !== null) {
            $from = $plan->target->rootPath.'/'.ModuleLayout::ROUTES.'/'.$plan->routeFileRenameFrom;
            $to = $plan->target->rootPath.'/'.ModuleLayout::ROUTES.'/'.$plan->routeFileRenameTo;

            if ($this->filesystem->exists($from)) {
                $this->filesystem->move($from, $to);
            }
        }

        $this->moduleRegistry->refresh();
        $this->metadataCompiler->compile();
    }

    private function assertCanRelocate(\Laravarc\Core\Module\ModuleIdentity $source, \Laravarc\Core\Module\ModuleIdentity $target): void
    {
        $this->moduleRegistry->requireByPath($source->path);

        if (! is_dir($source->rootPath)) {
            throw new ModuleRelocateException(sprintf(
                'Source module directory does not exist at [%s].',
                $source->rootPath,
            ));
        }

        if (is_dir($target->rootPath)) {
            throw new ModuleRelocateException(sprintf(
                'Target module path [%s] already exists. Refusing to overwrite.',
                $target->path,
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function listFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function routeFileRename(\Laravarc\Core\Module\ModuleIdentity $source, \Laravarc\Core\Module\ModuleIdentity $target): array
    {
        if ($source->entityName === $target->entityName) {
            return [null, null];
        }

        return [
            $source->entityName.'Route.php',
            $target->entityName.'Route.php',
        ];
    }

    /**
     * @return list<string>
     */
    private function searchRoots(): array
    {
        $roots = [];

        $appPath = realpath(app_path()) ?: app_path();
        if (is_dir($appPath)) {
            $roots[] = $appPath;
        }

        $modulesPath = realpath($this->modulesPath) ?: $this->modulesPath;
        if (is_dir($modulesPath) && ! $this->pathIsInside($modulesPath, $appPath)) {
            $roots[] = $modulesPath;
        }

        $routesPath = realpath(base_path('routes')) ?: base_path('routes');
        if (is_dir($routesPath)) {
            $roots[] = $routesPath;
        }

        return array_values(array_unique($roots));
    }

    private function pathIsInside(string $path, string $parent): bool
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedParent = $this->normalizePath($parent);

        return $normalizedPath === $normalizedParent
            || str_starts_with($normalizedPath.'/', $normalizedParent.'/');
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
