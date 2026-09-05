<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Filesystem\Filesystem;
use Laravarc\Core\Commands\Support\ModuleGenerationState;
use Laravarc\Core\Commands\Support\ModuleIdentityResolver;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLayout;

final readonly class ModuleMigrationResult
{
    /**
     * @param  list<string>  $modulePaths
     */
    public function __construct(
        public array $modulePaths,
        public bool $migrationsExecuted,
    ) {}
}

final class ModuleMigrator
{
    public function __construct(
        private readonly ModuleIdentityResolver $identityResolver,
        private readonly ModuleGenerationState $generationState,
        private readonly ModuleMaker $moduleMaker,
        private readonly Filesystem $filesystem,
        private readonly Artisan $artisan,
        private readonly string $modulesPath,
    ) {}

    /**
     * @return list<ModuleGenerationResult>
     */
    public function migrate(
        ?string $module,
        ?string $path,
        bool $force,
        bool $dryRun,
        ModuleGenerationOptions $generationOptions,
    ): array {
        $modulePaths = $this->resolveModulePaths($module, $path);
        $generationResults = [];

        foreach ($modulePaths as $modulePath) {
            $identity = $this->identityResolver->resolve($modulePath);
            $migrationPath = $this->migrationDirectory($identity);

            if (is_dir($migrationPath)) {
                if (! $dryRun) {
                    $exitCode = $this->artisan->call('migrate', [
                        '--path' => $migrationPath,
                        '--realpath' => true,
                        '--force' => $force,
                    ]);

                    if ($exitCode !== 0) {
                        throw new \RuntimeException(trim($this->artisan->output()) ?: 'Migration failed.');
                    }
                }
            }

            if ($this->generationState->needsFullGeneration($identity)) {
                $generationResults[] = $this->moduleMaker->continueAfterMigrate($identity, $generationOptions);
            }
        }

        return $generationResults;
    }

    /**
     * @return list<string>
     */
    private function resolveModulePaths(?string $module, ?string $path): array
    {
        if ($path !== null && $path !== '') {
            return [trim($path, '/')];
        }

        if ($module !== null && $module !== '') {
            return [trim($module, '/')];
        }

        return $this->discoverModulePathsWithMigrations();
    }

    /**
     * @return list<string>
     */
    private function discoverModulePathsWithMigrations(): array
    {
        $paths = [];

        if (! is_dir($this->modulesPath)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->modulesPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPath(), strlen($this->modulesPath) + 1));

            if (str_ends_with($relative, '/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS)) {
                $modulePath = dirname($relative, 2);
                $paths[$modulePath] = $modulePath;
            }
        }

        return array_values($paths);
    }

    private function migrationDirectory(ModuleIdentity $identity): string
    {
        return $identity->rootPath.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS;
    }
}
