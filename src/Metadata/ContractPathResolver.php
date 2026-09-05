<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Laravarc\Core\Support\CorePathResolver;

final class ContractPathResolver
{
    private readonly string $resolvedSharedPath;

    public function __construct(
        string $sharedPath,
    ) {
        $this->resolvedSharedPath = CorePathResolver::resolve($sharedPath);
    }

    public function contractNamespace(string $modulePath): string
    {
        $sharedNamespace = CorePathResolver::namespaceFromPath($this->resolvedSharedPath);

        return trim('App\\'.$sharedNamespace.'\\'.str_replace('/', '\\', $modulePath).'\\Contracts', '\\');
    }

    public function contractDirectory(string $modulePath): string
    {
        return $this->resolvedSharedPath.'/'.$modulePath.'/Contracts';
    }

    public function contractPath(string $modulePath, string $interfaceName): string
    {
        return $this->contractDirectory($modulePath).'/'.$interfaceName.'.php';
    }
}
