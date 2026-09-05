<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

use Laravarc\Core\Generation\Metadata\MetadataSelection;
use Laravarc\Core\Schema\SchemaSnapshot;

/**
 * Immutable generation payload. Contains data only — no services, container, or generator instances.
 */
final readonly class GenerationContext
{
    /**
     * @param  list<string>  $selectedGenerators
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $controllerReturns
     * @param  array<string, array{className: string, relativePath: string, shortName: string}>  $resolvedClasses
     * @param  list<string>  $formRequestActions
     */
    public function __construct(
        public string $modulePath,
        public string $moduleKey,
        public string $moduleNamespace,
        public string $moduleName,
        public string $filesystemRoot,
        public string $tableName,
        public ?string $connection,
        public ?SchemaSnapshot $schemaSnapshot,
        public string $presentationStack,
        public string $selectedPreset,
        public array $selectedGenerators,
        public array $config,
        public bool $refresh,
        public bool $migrationOnly,
        public bool $tableExists,
        public array $controllerReturns,
        public array $resolvedClasses,
        public array $formRequestActions,
        public string $entityVariable,
        public string $collectionVariable,
        public ?string $selectedLocale,
        public MetadataSelection $metadataSelection,
        public bool $splitServices = false,
        public bool $withContractAttributes = false,
        public bool $withEvents = false,
        public bool $withExtension = false,
    ) {}

    public function classFor(string $key): array
    {
        if (! isset($this->resolvedClasses[$key])) {
            throw new \InvalidArgumentException(sprintf('Resolved class [%s] is not available in generation context.', $key));
        }

        return $this->resolvedClasses[$key];
    }

    public function hasClass(string $key): bool
    {
        return isset($this->resolvedClasses[$key]);
    }

    public function stubPath(string $generatorName): string
    {
        $paths = $this->config['stub_paths'] ?? [];

        if (! is_array($paths) || ! isset($paths[$generatorName]) || ! is_string($paths[$generatorName])) {
            throw new \InvalidArgumentException(sprintf('Stub path for generator [%s] is not available in generation context.', $generatorName));
        }

        return $paths[$generatorName];
    }

    public function namedStubPath(string $name): string
    {
        $paths = $this->config['stub_paths'] ?? [];

        if (! is_array($paths) || ! isset($paths[$name]) || ! is_string($paths[$name])) {
            throw new \InvalidArgumentException(sprintf('Stub path [%s] is not available in generation context.', $name));
        }

        return $paths[$name];
    }
}
