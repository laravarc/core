<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

final class FqcnNamespaceReplacer
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * @param  list<string>  $searchRoots
     * @return list<string>
     */
    public function findFilesContainingNamespace(array $searchRoots, string $namespace): array
    {
        $roots = array_values(array_filter($searchRoots, static fn (string $root): bool => is_dir($root)));

        if ($roots === []) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->in($roots)
            ->name('*.php');

        $matches = [];

        foreach ($finder as $file) {
            $path = $file->getPathname();

            if ($this->containsNamespaceReference((string) $this->filesystem->get($path), $namespace)) {
                $matches[] = $path;
            }
        }

        sort($matches);

        return $matches;
    }

    public function containsNamespaceReference(string $contents, string $namespace): bool
    {
        return preg_match($this->pattern($namespace), $contents) === 1;
    }

    public function replaceInFile(string $path, string $oldNamespace, string $newNamespace): bool
    {
        $contents = (string) $this->filesystem->get($path);
        $updated = $this->replaceInContents($contents, $oldNamespace, $newNamespace);

        if ($updated === $contents) {
            return false;
        }

        $this->filesystem->put($path, $updated);

        return true;
    }

    public function replaceInContents(string $contents, string $oldNamespace, string $newNamespace): string
    {
        return preg_replace($this->pattern($oldNamespace), $newNamespace, $contents) ?? $contents;
    }

    private function pattern(string $namespace): string
    {
        return '/'.preg_quote($namespace, '/').'(?=\\\\|;|::)/';
    }
}
