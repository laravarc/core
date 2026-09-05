<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Illuminate\Contracts\Console\Kernel as Artisan;
use Laravarc\Core\Commands\Support\ModuleIdentityResolver;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLayout;

final readonly class ModuleSeedResult
{
    /**
     * @param  list<string>  $seeders
     */
    public function __construct(
        public string $modulePath,
        public array $seeders,
    ) {}
}

final class ModuleSeeder
{
    public function __construct(
        private readonly ModuleIdentityResolver $identityResolver,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly Artisan $artisan,
        private readonly string $modulesPath,
    ) {}

    /**
     * @return list<ModuleSeedResult>
     */
    public function seed(?string $modulePath, bool $force, bool $dryRun): array
    {
        $paths = $modulePath !== null && $modulePath !== ''
            ? [trim($modulePath, '/')]
            : $this->discoverModulePathsWithSeeders();

        if ($paths === []) {
            throw new \RuntimeException('No module seeders were found.');
        }

        $results = [];

        foreach ($paths as $path) {
            $identity = $this->identityResolver->resolve($path);

            if ($modulePath !== null && $modulePath !== '') {
                $this->moduleRegistry->requireByPath($path);
            }

            $seeders = $this->resolveSeederClasses($identity);

            if ($seeders === []) {
                if ($modulePath !== null && $modulePath !== '') {
                    throw new \RuntimeException(sprintf(
                        'Module [%s] has no seeders in Database/Seeders/.',
                        $path,
                    ));
                }

                continue;
            }

            foreach ($seeders as $seederClass) {
                if (! $dryRun) {
                    $exitCode = $this->artisan->call('db:seed', [
                        '--class' => $seederClass,
                        '--force' => $force,
                    ]);

                    if ($exitCode !== 0) {
                        throw new \RuntimeException(trim($this->artisan->output()) ?: 'Seeding failed.');
                    }
                }
            }

            $results[] = new ModuleSeedResult($path, $seeders);
        }

        if ($results === []) {
            throw new \RuntimeException('No module seeders were found.');
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function discoverModulePathsWithSeeders(): array
    {
        $paths = [];

        if (! is_dir($this->modulesPath)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->modulesPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Seeder.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPath(), strlen($this->modulesPath) + 1));

            if (str_ends_with($relative, '/'.ModuleLayout::DATABASE.'/'.ModuleLayout::SEEDERS)) {
                $modulePath = dirname($relative, 2);
                $paths[$modulePath] = $modulePath;
            }
        }

        return array_values($paths);
    }

    /**
     * @return list<string>
     */
    private function resolveSeederClasses(ModuleIdentity $identity): array
    {
        $directory = $identity->rootPath.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::SEEDERS;

        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];

        foreach (glob($directory.'/*Seeder.php') ?: [] as $file) {
            $classes[] = $identity->namespace.'\\Database\\Seeders\\'.basename($file, '.php');
        }

        sort($classes);

        return $classes;
    }
}
