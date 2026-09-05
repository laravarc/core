<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laravarc\Core\Discovery\ModuleManifestEntry;
use Laravarc\Core\Discovery\ModuleRegistry;
use Laravarc\Core\Metadata\Attributes\CommandContract;
use Laravarc\Core\Metadata\Attributes\QueryContract;
use Laravarc\Core\Metadata\ContractInterfaceReader;
use Laravarc\Core\Metadata\ContractPathResolver;
use Laravarc\Core\Support\CorePathResolver;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class ContractSyncService
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly Filesystem $filesystem,
        private readonly ContractPathResolver $contractPaths,
        private readonly ContractInterfaceReader $interfaceReader = new ContractInterfaceReader,
    ) {}

    /**
     * @return list<string>
     */
    public function sync(?string $modulePath = null): array
    {
        $entries = $modulePath !== null && trim($modulePath) !== ''
            ? [$this->moduleRegistry->requireByPath($modulePath)]
            : $this->moduleRegistry->all();

        $generated = [];

        foreach ($entries as $entry) {
            $generated = [...$generated, ...$this->syncModule($entry)];
        }

        return $generated;
    }

    /**
     * @return list<string>
     */
    private function syncModule(ModuleManifestEntry $entry): array
    {
        $entity = $this->entityName($entry);
        $generated = [];

        $commandClass = $entry->namespace.'\\Services\\Commands\\'.$entity.'CommandService';
        $queryClass = $entry->namespace.'\\Services\\Queries\\'.$entity.'QueryService';

        $generated = [...$generated, ...$this->syncContractFor(
            className: $commandClass,
            attribute: CommandContract::class,
            interfaceName: $entity.'CommandServiceContract',
            entry: $entry,
        )];
        $generated = [...$generated, ...$this->syncContractFor(
            className: $queryClass,
            attribute: QueryContract::class,
            interfaceName: $entity.'QueryServiceContract',
            entry: $entry,
        )];

        return $generated;
    }

    /**
     * @return list<string>
     */
    private function syncContractFor(
        string $className,
        string $attribute,
        string $interfaceName,
        ModuleManifestEntry $entry,
    ): array {
        $classPath = $this->classPath($className, $entry);
        $contractPath = $this->contractPath($entry, $interfaceName);

        if (! $this->filesystem->exists($classPath)) {
            if ($this->filesystem->exists($contractPath)) {
                $this->filesystem->delete($contractPath);

                return [$contractPath];
            }

            return [];
        }

        require_once $classPath;

        if (! class_exists($className)) {
            return [];
        }

        $reflection = new ReflectionClass($className);
        $methods = array_values(array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $reflection->getName()
                && $method->getAttributes($attribute) !== [],
        ));

        if ($methods === []) {
            if ($this->filesystem->exists($contractPath)) {
                $this->filesystem->delete($contractPath);

                return [$contractPath];
            }

            return [];
        }

        if ($this->filesystem->exists($contractPath) && $this->shouldSkipExistingContract($contractPath, $interfaceName, $methods)) {
            return [];
        }

        $existingContents = $this->filesystem->exists($contractPath)
            ? (string) $this->filesystem->get($contractPath)
            : '';

        $this->filesystem->ensureDirectoryExists(dirname($contractPath));
        $this->filesystem->put(
            $contractPath,
            $this->renderInterface($entry, $interfaceName, $methods, $existingContents),
        );

        return [$contractPath];
    }

    /**
     * @param  list<ReflectionMethod>  $methods
     */
    private function renderInterface(
        ModuleManifestEntry $entry,
        string $interfaceName,
        array $methods,
        string $existingContents,
    ): string {
        $namespace = $this->contractNamespace($entry);
        $imports = $this->mergeImports(
            $this->parseUseImports($existingContents),
            $this->collectClassTypes($methods),
            $namespace,
        );
        $existingDocs = $this->parseMethodDocblocks($existingContents);
        $existingMethodNames = $this->methodNamesFromContents($existingContents);

        $methodBlocks = [];

        foreach ($methods as $method) {
            $doc = $existingDocs[$method->getName()] ?? null;
            if ($doc === null && ! in_array($method->getName(), $existingMethodNames, true)) {
                $serviceDoc = $method->getDocComment();
                $doc = is_string($serviceDoc) && $serviceDoc !== '' ? $serviceDoc : null;
            }

            $signature = '    public function '.$method->getName().'('.$this->renderParameters($method, $imports, $namespace).'): '
                .$this->renderType($method->getReturnType(), $imports, $namespace).';';

            $methodBlocks[] = $doc === null
                ? $signature
                : $this->indentDocblock($doc).PHP_EOL.$signature;
        }

        $header = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace '.$namespace.';',
            '',
        ];

        $useLines = $this->renderUseStatements($imports);
        if ($useLines !== []) {
            $header = [...$header, ...$useLines, ''];
        }

        return implode(PHP_EOL, [
            ...$header,
            'interface '.$interfaceName,
            '{',
            implode(PHP_EOL.PHP_EOL, $methodBlocks),
            '}',
            '',
        ]);
    }

    /**
     * @param  array<string, string>  $imports
     */
    private function renderParameters(ReflectionMethod $method, array $imports, string $contractNamespace): string
    {
        return implode(', ', array_map(
            fn (ReflectionParameter $parameter): string => $this->renderParameter($parameter, $imports, $contractNamespace),
            $method->getParameters(),
        ));
    }

    /**
     * @param  array<string, string>  $imports
     */
    private function renderParameter(ReflectionParameter $parameter, array $imports, string $contractNamespace): string
    {
        $type = $this->renderType($parameter->getType(), $imports, $contractNamespace);
        $byRef = $parameter->isPassedByReference() ? '&' : '';
        $variadic = $parameter->isVariadic() ? '...' : '';
        $default = '';

        if ($parameter->isOptional() && ! $parameter->isVariadic() && $parameter->isDefaultValueAvailable()) {
            $default = ' = '.$this->renderDefaultValue($parameter->getDefaultValue());
        }

        return trim($type.' '.$byRef.$variadic.'$'.$parameter->getName().$default);
    }

    /**
     * @param  array<string, string>  $imports
     */
    private function renderType(?ReflectionType $type, array $imports, string $contractNamespace): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            if ($name === 'mixed') {
                return 'mixed';
            }

            $nullable = $type->allowsNull() && $name !== 'null' ? '?' : '';

            if ($type->isBuiltin()) {
                return $nullable.$name;
            }

            return $nullable.$this->shortNameFor($name, $imports, $contractNamespace);
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn (ReflectionType $item): string => ltrim($this->renderType($item, $imports, $contractNamespace), '?'),
                $type->getTypes(),
            ));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(
                fn (ReflectionType $item): string => $this->renderType($item, $imports, $contractNamespace),
                $type->getTypes(),
            ));
        }

        return 'mixed';
    }

    /**
     * @param  array<string, string>  $imports alias => FQCN
     */
    private function shortNameFor(string $fqcn, array $imports, string $contractNamespace): string
    {
        $fqcn = ltrim($fqcn, '\\');

        foreach ($imports as $alias => $imported) {
            if ($imported === $fqcn) {
                return $alias;
            }
        }

        $basename = $this->classBasename($fqcn);
        $typeNamespace = $this->classNamespace($fqcn);

        if ($typeNamespace === $contractNamespace) {
            return $basename;
        }

        return '\\'.$fqcn;
    }

    /**
     * @param  list<ReflectionMethod>  $methods
     * @return list<string>
     */
    private function collectClassTypes(array $methods): array
    {
        $names = [];

        foreach ($methods as $method) {
            foreach ($method->getParameters() as $parameter) {
                $this->collectTypeNames($parameter->getType(), $names);
            }

            $this->collectTypeNames($method->getReturnType(), $names);
        }

        $keys = array_keys($names);
        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string, true>  $names
     */
    private function collectTypeNames(?ReflectionType $type, array &$names): void
    {
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $names[ltrim($type->getName(), '\\')] = true;

            return;
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $inner) {
                $this->collectTypeNames($inner, $names);
            }
        }
    }

    /**
     * @return array<string, string> alias => FQCN
     */
    private function parseUseImports(string $contents): array
    {
        if ($contents === '' || preg_match_all('/^use\s+([^;]+);/m', $contents, $matches) === 0) {
            return [];
        }

        $imports = [];

        foreach ($matches[1] as $clause) {
            $clause = trim($clause);

            if (str_starts_with($clause, 'function ') || str_starts_with($clause, 'const ')) {
                continue;
            }

            if (preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/', $clause, $aliasMatch) === 1) {
                $imports[$aliasMatch[2]] = ltrim(trim($aliasMatch[1]), '\\');

                continue;
            }

            $fqcn = ltrim($clause, '\\');
            $imports[$this->classBasename($fqcn)] = $fqcn;
        }

        return $imports;
    }

    /**
     * @return array<string, string> method name => raw docblock
     */
    private function parseMethodDocblocks(string $contents): array
    {
        if ($contents === '' || preg_match_all(
            '/public\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
            $contents,
            $matches,
            PREG_OFFSET_CAPTURE,
        ) === 0) {
            return [];
        }

        $docs = [];

        foreach ($matches[1] as $index => $match) {
            $methodName = $match[0];
            $offset = (int) $matches[0][$index][1];
            $before = substr($contents, 0, $offset);

            if (preg_match('/(\/\*\*(?:[^*]|\*(?!\/))*\*\/)\s*$/s', $before, $docMatch) === 1) {
                $docs[$methodName] = $docMatch[1];
            }
        }

        return $docs;
    }

    /**
     * @return list<string>
     */
    private function methodNamesFromContents(string $contents): array
    {
        if (preg_match_all('/public\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $contents, $matches) === 0) {
            return [];
        }

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @param  array<string, string>  $existing alias => FQCN
     * @param  list<string>  $neededFqcns
     * @return array<string, string>
     */
    private function mergeImports(array $existing, array $neededFqcns, string $contractNamespace): array
    {
        $imports = $existing;
        $usedShort = array_map(static fn (string $alias): string => strtolower($alias), array_keys($imports));

        foreach ($neededFqcns as $fqcn) {
            $fqcn = ltrim($fqcn, '\\');

            if (in_array($fqcn, $imports, true)) {
                continue;
            }

            if ($this->classNamespace($fqcn) === $contractNamespace) {
                continue;
            }

            $short = $this->classBasename($fqcn);

            if (in_array(strtolower($short), $usedShort, true)) {
                continue;
            }

            $imports[$short] = $fqcn;
            $usedShort[] = strtolower($short);
        }

        return $imports;
    }

    /**
     * @param  array<string, string>  $imports
     * @return list<string>
     */
    private function renderUseStatements(array $imports): array
    {
        if ($imports === []) {
            return [];
        }

        asort($imports);

        $lines = [];

        foreach ($imports as $alias => $fqcn) {
            if ($alias === $this->classBasename($fqcn)) {
                $lines[] = 'use '.$fqcn.';';

                continue;
            }

            $lines[] = 'use '.$fqcn.' as '.$alias.';';
        }

        return $lines;
    }

    private function indentDocblock(string $docblock): string
    {
        $lines = preg_split('/\R/', trim($docblock)) ?: [];
        $indented = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            $indented[] = str_starts_with($trimmed, '*') && ! str_starts_with($trimmed, '/**')
                ? '     '.$trimmed
                : '    '.$trimmed;
        }

        return implode(PHP_EOL, $indented);
    }

    private function renderDefaultValue(mixed $value): string
    {
        if ($value === []) {
            return '[]';
        }

        return var_export($value, true);
    }

    private function classBasename(string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function classNamespace(string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }

    private function classPath(string $className, ModuleManifestEntry $entry): string
    {
        $relative = str_replace($entry->namespace.'\\', '', $className);
        $relative = str_replace('\\', '/', $relative).'.php';

        return $entry->rootPath.'/'.$relative;
    }

    private function contractPath(ModuleManifestEntry $entry, string $interfaceName): string
    {
        return $this->contractPaths->contractPath($entry->path, $interfaceName);
    }

    private function contractNamespace(ModuleManifestEntry $entry): string
    {
        return $this->contractPaths->contractNamespace($entry->path);
    }

    private function entityName(ModuleManifestEntry $entry): string
    {
        $segments = explode('/', $entry->path);
        $lastSegment = (string) (end($segments) ?: $entry->path);

        return Str::studly(Str::singular($lastSegment));
    }

    /**
     * @param  list<ReflectionMethod>  $serviceMethods
     */
    private function shouldSkipExistingContract(string $contractPath, string $expectedInterfaceName, array $serviceMethods): bool
    {
        if (! $this->isManagedContractPath($contractPath)) {
            return true;
        }

        $existingInterfaceName = $this->interfaceReader->interfaceName($contractPath);

        if ($existingInterfaceName !== null && $existingInterfaceName !== $expectedInterfaceName) {
            return true;
        }

        $existingMethodNames = $this->interfaceReader->methodNames($contractPath);
        $serviceMethodNames = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $serviceMethods,
        );

        return array_diff($existingMethodNames, $serviceMethodNames) !== [];
    }

    private function isManagedContractPath(string $contractPath): bool
    {
        $sharedRoot = CorePathResolver::resolve((string) config('laravarc.shared_path', app_path('Shared')));
        $normalized = str_replace('\\', '/', $contractPath);

        return str_starts_with($normalized, $sharedRoot.'/')
            && str_contains($normalized, '/Contracts/');
    }
}
