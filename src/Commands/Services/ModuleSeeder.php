<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Illuminate\Contracts\Console\Kernel as Artisan;
use Laravarc\Core\Commands\Support\ModuleIdentityResolver;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Metadata\Attributes\SeedPriority;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLayout;
use ReflectionAttribute;
use ReflectionClass;

final readonly class ModuleSeedResult
{
    public function __construct(
        public string $modulePath,
        public string $seederClass,
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
     * Collect, flatten, priority-sort, then run (or dry-run) module seeders.
     *
     * @return list<ModuleSeedResult> Final execution order
     */
    public function seed(?string $modulePath, bool $force, bool $dryRun): array
    {
        $entries = $this->collectSeederEntries($modulePath);

        if ($entries === []) {
            throw new \RuntimeException(
                $modulePath !== null && $modulePath !== ''
                    ? sprintf('Module [%s] has no seeders in Database/Seeders/.', trim($modulePath, '/'))
                    : 'No module seeders were found.',
            );
        }

        $ordered = $this->sortSeederEntries($entries);
        $results = [];

        foreach ($ordered as $entry) {
            if (! $dryRun) {
                $exitCode = $this->artisan->call('db:seed', [
                    '--class' => $entry['class'],
                    '--force' => $force,
                ]);

                if ($exitCode !== 0) {
                    throw new \RuntimeException(trim($this->artisan->output()) ?: 'Seeding failed.');
                }
            }

            $results[] = new ModuleSeedResult($entry['modulePath'], $entry['class']);
        }

        return $results;
    }

    /**
     * @param  list<array{modulePath: string, class: class-string}>  $entries
     * @return list<array{modulePath: string, class: class-string}>
     */
    public function sortSeederEntries(array $entries): array
    {
        $withPriority = [];
        $withoutPriority = [];

        foreach ($entries as $entry) {
            $priority = $this->resolvePriority($entry['class']);

            if ($priority !== null) {
                $withPriority[] = ['entry' => $entry, 'priority' => $priority];
            } else {
                $withoutPriority[] = $entry;
            }
        }

        usort($withPriority, static function (array $a, array $b): int {
            $priorityCompare = $b['priority'] <=> $a['priority'];

            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return $a['entry']['class'] <=> $b['entry']['class'];
        });

        usort($withoutPriority, static fn (array $a, array $b): int => $a['class'] <=> $b['class']);

        return [
            ...array_map(static fn (array $row): array => $row['entry'], $withPriority),
            ...$withoutPriority,
        ];
    }

    /**
     * @return list<array{modulePath: string, class: class-string}>
     */
    private function collectSeederEntries(?string $modulePath): array
    {
        $scoped = $modulePath !== null && $modulePath !== '';
        $paths = $scoped
            ? [trim($modulePath, '/')]
            : $this->discoverModulePathsWithSeeders();

        if ($paths === []) {
            return [];
        }

        $entries = [];

        foreach ($paths as $path) {
            $identity = $this->identityResolver->resolve($path);

            if ($scoped) {
                $this->moduleRegistry->requireByPath($path);
            }

            foreach ($this->resolveSeederClasses($identity) as $class) {
                $entries[] = [
                    'modulePath' => $identity->path,
                    'class' => $class,
                ];
            }
        }

        return $entries;
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
     * @return list<class-string>
     */
    private function resolveSeederClasses(ModuleIdentity $identity): array
    {
        $directory = $identity->rootPath.'/'.ModuleLayout::DATABASE.'/'.ModuleLayout::SEEDERS;

        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];

        foreach (glob($directory.'/*Seeder.php') ?: [] as $file) {
            $class = $identity->namespace.'\\Database\\Seeders\\'.basename($file, '.php');

            if (! class_exists($class, false)) {
                require_once $file;
            }

            if (! class_exists($class, false) && ! class_exists($class, true)) {
                throw new \RuntimeException(sprintf(
                    'Seeder file [%s] does not define class [%s].',
                    $file,
                    $class,
                ));
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @param  class-string  $class
     */
    private function resolvePriority(string $class): ?int
    {
        $attributes = (new ReflectionClass($class))->getAttributes(SeedPriority::class);

        if ($attributes === []) {
            return null;
        }

        /** @var ReflectionAttribute<SeedPriority> $attribute */
        $attribute = $attributes[0];

        return $attribute->newInstance()->priority;
    }
}
