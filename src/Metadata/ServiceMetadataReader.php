<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Illuminate\Support\Str;
use Laravarc\Core\Discovery\ModuleManifestEntry;

final class ServiceMetadataReader
{
    public function __construct(
        private readonly ContractPathResolver $contractPaths,
    ) {}

    /**
     * @return list<array{concrete: string, contract: string, kind: string}>
     */
    public function readModule(ModuleManifestEntry $entry): array
    {
        $entity = $this->entityName($entry);
        $bindings = [];

        $commandConcrete = $entry->namespace.'\\Services\\Commands\\'.$entity.'CommandService';
        $commandContract = $this->contractPaths->contractNamespace($entry->path).'\\'.$entity.'CommandServiceContract';
        $commandBinding = $this->resolveBinding(
            concrete: $commandConcrete,
            contract: $commandContract,
            concretePath: $entry->rootPath.'/Services/Commands/'.$entity.'CommandService.php',
            contractPath: $this->contractPaths->contractPath($entry->path, $entity.'CommandServiceContract'),
            kind: 'command',
        );

        if ($commandBinding !== null) {
            $bindings[] = $commandBinding;
        }

        $queryConcrete = $entry->namespace.'\\Services\\Queries\\'.$entity.'QueryService';
        $queryContract = $this->contractPaths->contractNamespace($entry->path).'\\'.$entity.'QueryServiceContract';
        $queryBinding = $this->resolveBinding(
            concrete: $queryConcrete,
            contract: $queryContract,
            concretePath: $entry->rootPath.'/Services/Queries/'.$entity.'QueryService.php',
            contractPath: $this->contractPaths->contractPath($entry->path, $entity.'QueryServiceContract'),
            kind: 'query',
        );

        if ($queryBinding !== null) {
            $bindings[] = $queryBinding;
        }

        return $bindings;
    }

    public function hasServiceSignals(ModuleManifestEntry $entry): bool
    {
        if ($this->readModule($entry) !== []) {
            return true;
        }

        $entity = $this->entityName($entry);

        return is_file($entry->rootPath.'/Services/Commands/'.$entity.'CommandService.php')
            || is_file($entry->rootPath.'/Services/Queries/'.$entity.'QueryService.php')
            || is_file($this->contractPaths->contractPath($entry->path, $entity.'CommandServiceContract'))
            || is_file($this->contractPaths->contractPath($entry->path, $entity.'QueryServiceContract'));
    }

    /**
     * @return array{concrete: string, contract: string, kind: string}|null
     */
    private function resolveBinding(
        string $concrete,
        string $contract,
        string $concretePath,
        string $contractPath,
        string $kind,
    ): ?array {
        if (! is_file($concretePath) || ! is_file($contractPath)) {
            return null;
        }

        return [
            'concrete' => $concrete,
            'contract' => $contract,
            'kind' => $kind,
        ];
    }

    private function entityName(ModuleManifestEntry $entry): string
    {
        $segments = explode('/', $entry->path);
        $lastSegment = (string) (end($segments) ?: $entry->path);

        return Str::studly(Str::singular($lastSegment));
    }
}
