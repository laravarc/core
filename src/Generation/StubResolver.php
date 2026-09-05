<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

use RuntimeException;

final class StubResolver
{
    public function __construct(
        private readonly string $builtinPath,
        private readonly ?string $publishedPath = null,
        private readonly ?string $overridePath = null,
    ) {}

    public function resolve(string $stubName): string
    {
        foreach ($this->candidatePaths($stubName) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException(sprintf('Unable to locate stub [%s].', $stubName));
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(string $stubName): array
    {
        $paths = [];

        if ($this->overridePath !== null && $this->overridePath !== '') {
            $paths[] = rtrim($this->overridePath, '/').'/'.$stubName;
        }

        if ($this->publishedPath !== null && $this->publishedPath !== '') {
            $paths[] = rtrim($this->publishedPath, '/').'/'.$stubName;
        }

        $paths[] = rtrim($this->builtinPath, '/').'/'.$stubName;

        return $paths;
    }
}
