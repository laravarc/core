<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

use Laravarc\Core\Convention\Layer;
use Laravarc\Core\Contracts\LayerResolver;
use Laravarc\Core\Contracts\ModuleKeyResolver;
use Laravarc\Core\Contracts\RequestResolver;
use Laravarc\Core\Generation\Metadata\MetadataSelection;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Presentation\ApiStack;
use Laravarc\Core\Presentation\BladeStack;
use Laravarc\Core\Presentation\PresentationGenerationContext;
use Laravarc\Core\Presentation\PresentationGenerationContextFactory;
use Laravarc\Core\Schema\SchemaSnapshot;
use Laravarc\Core\Support\CorePathResolver;

final class GenerationContextFactory
{
    /**
     * @param  list<string>  $only
     * @param  list<string>  $except
     */
    public function __construct(
        private readonly ModuleKeyResolver $moduleKeyResolver,
        private readonly LayerResolver $layerResolver,
        private readonly RequestResolver $requestResolver,
        private readonly PresentationGenerationContextFactory $presentationContextFactory,
        private readonly GeneratorRegistry $generatorRegistry,
        private readonly StubResolver $stubResolver,
    ) {}

    /**
     * @param  list<string>  $only
     * @param  list<string>  $except
     * @param  array<string, mixed>  $config
     */
    public function make(
        ModuleIdentity $identity,
        ?SchemaSnapshot $schemaSnapshot,
        string $tableName,
        ?string $connection,
        bool $tableExists,
        string $preset,
        string $presentationStack,
        bool $refresh,
        array $only,
        array $except,
        ?string $selectedLocale,
        array $config,
        ?MetadataSelection $metadataSelection = null,
        bool $withContractAttributes = false,
        bool $withExtension = false,
    ): GenerationContext {
        $moduleKey = $this->moduleKeyResolver->resolve($identity);
        $presentationContext = $this->presentationContextFactory->make($identity);
        $migrationOnly = ! $tableExists && $schemaSnapshot === null;
        $splitServices = ! is_file($identity->rootPath.'/Services/'.$identity->entityName.'Service.php');

        $resolution = $this->generatorRegistry->resolve(
            preset: $preset,
            presentationStack: $presentationStack,
            migrationOnly: $migrationOnly,
            refresh: $refresh,
            tableExists: $tableExists,
            selectedLocale: $selectedLocale,
            only: $only,
            except: $except,
            withExtension: $withExtension,
        );

        $config['warnings'] = $resolution->warnings;
        $config['stub_paths'] = $this->resolveStubPaths();
        $withEvents = in_array(GeneratorName::EVENT, $resolution->generators, true);

        return new GenerationContext(
            modulePath: $identity->path,
            moduleKey: $moduleKey,
            moduleNamespace: $identity->namespace,
            moduleName: $identity->entityName,
            filesystemRoot: $identity->rootPath,
            tableName: $tableName,
            connection: $connection,
            schemaSnapshot: $schemaSnapshot,
            presentationStack: $presentationStack,
            selectedPreset: $preset,
            selectedGenerators: $resolution->generators,
            config: $config,
            refresh: $refresh,
            migrationOnly: $migrationOnly,
            tableExists: $tableExists,
            controllerReturns: $this->controllerReturns($presentationStack, $presentationContext),
            resolvedClasses: $this->resolveClasses($identity, $presentationStack, $splitServices),
            formRequestActions: ['store', 'update', 'destroy'],
            entityVariable: $presentationContext->entityVariable,
            collectionVariable: $presentationContext->collectionVariable,
            selectedLocale: $selectedLocale,
            metadataSelection: $metadataSelection ?? MetadataSelection::empty(),
            splitServices: $splitServices,
            withContractAttributes: $withContractAttributes,
            withEvents: $withEvents,
            withExtension: $withExtension,
        );
    }

    /**
     * @return array<string, string>
     */
    private function controllerReturns(string $stack, PresentationGenerationContext $context): array
    {
        $stackInstance = $stack === 'blade' ? new BladeStack : new ApiStack;
        $actions = $stack === 'blade'
            ? ['index', 'create', 'show', 'edit', 'store', 'update', 'destroy']
            : ['index', 'show', 'store', 'update', 'destroy'];
        $returns = [];

        foreach ($actions as $action) {
            $returns[$action] = $stackInstance->controllerReturn($action, $context);
        }

        return $returns;
    }

    /**
     * @return array<string, array{className: string, relativePath: string, shortName: string}>
     */
    private function resolveClasses(ModuleIdentity $identity, string $presentationStack, bool $splitServices): array
    {
        $resolved = [];

        foreach ([
            'model' => Layer::Model,
            'repository' => Layer::Repository,
            'controller' => Layer::Controller,
            'policy' => Layer::Policy,
        ] as $key => $layer) {
            $resolved[$key] = $this->toResolvedEntry($this->layerResolver->resolve($identity, $layer));
        }

        if ($splitServices) {
            $resolved['command_service'] = [
                'className' => $identity->namespace.'\\Services\\Commands\\'.$identity->entityName.'CommandService',
                'relativePath' => 'Services/Commands/'.$identity->entityName.'CommandService.php',
                'shortName' => $identity->entityName.'CommandService',
            ];
            $resolved['query_service'] = [
                'className' => $identity->namespace.'\\Services\\Queries\\'.$identity->entityName.'QueryService',
                'relativePath' => 'Services/Queries/'.$identity->entityName.'QueryService.php',
                'shortName' => $identity->entityName.'QueryService',
            ];
        } else {
            $resolved['service'] = $this->toResolvedEntry($this->layerResolver->resolve($identity, Layer::Service));
        }

        if ($presentationStack === 'api') {
            $resolved['resource'] = $this->toResolvedEntry($this->layerResolver->resolve($identity, Layer::Resource));
        }

        foreach (['store', 'update', 'destroy'] as $action) {
            $resolved['form_request_'.$action] = $this->toResolvedEntry(
                $this->requestResolver->resolve($identity, $action),
            );
        }

        $eventName = $identity->entityName.'CreatedEvent';
        $eventDeletedName = $identity->entityName.'DeletedEvent';
        $listenerName = 'Log'.$identity->entityName.'CreatedListener';

        $sharedPath = CorePathResolver::resolve((string) config('laravarc.shared_path', app_path('Shared')));
        $sharedNamespace = trim('App\\'.CorePathResolver::namespaceFromPath($sharedPath), '\\');
        $moduleNamespacePath = str_replace('/', '\\', $identity->path);
        $eventsNamespace = $sharedNamespace.'\\'.$moduleNamespacePath.'\\Events';
        $eventsDirectory = $sharedPath.'/'.$identity->path.'/Events';

        $resolved['event'] = [
            'className' => $eventsNamespace.'\\'.$eventName,
            'relativePath' => 'Shared/'.$identity->path.'/Events/'.$eventName.'.php',
            'shortName' => $eventName,
            'absolutePath' => $eventsDirectory.'/'.$eventName.'.php',
        ];
        $resolved['event_deleted'] = [
            'className' => $eventsNamespace.'\\'.$eventDeletedName,
            'relativePath' => 'Shared/'.$identity->path.'/Events/'.$eventDeletedName.'.php',
            'shortName' => $eventDeletedName,
            'absolutePath' => $eventsDirectory.'/'.$eventDeletedName.'.php',
        ];
        $resolved['listener'] = $this->toResolvedEntry(
            $this->layerResolver->resolve($identity, Layer::Listener, $listenerName),
        );

        $seederClass = $identity->entityName.'Seeder';
        $resolved['seeder'] = [
            'className' => $identity->namespace.'\\Database\\Seeders\\'.$seederClass,
            'relativePath' => 'Database/Seeders/'.$seederClass.'.php',
            'shortName' => $seederClass,
        ];

        return $resolved;
    }

    /**
     * @return array{className: string, relativePath: string, shortName: string}
     */
    private function toResolvedEntry(\Laravarc\Core\Convention\ResolvedClass $resolved): array
    {
        return [
            'className' => $resolved->className,
            'relativePath' => $resolved->relativePath,
            'shortName' => class_basename($resolved->className),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveStubPaths(): array
    {
        $paths = [];

        foreach (GeneratorName::all() as $generator) {
            $paths[$generator] = $this->stubResolver->resolve($this->stubFileName($generator));
        }

        $paths['command_service'] = $this->stubResolver->resolve('command-service.stub');
        $paths['query_service'] = $this->stubResolver->resolve('query-service.stub');
        $paths['command_contract_attribute'] = $this->stubResolver->resolve('command-contract-attribute.stub');
        $paths['query_contract_attribute'] = $this->stubResolver->resolve('query-contract-attribute.stub');
        $paths['route_blade'] = $this->stubResolver->resolve('route.blade.stub');
        $paths['controller_blade'] = $this->stubResolver->resolve('controller.blade.stub');
        $paths['service-provider'] = $paths[GeneratorName::SERVICE_PROVIDER];
        $paths['service-provider.with-extension'] = $this->stubResolver->resolve('service-provider.with-extension.stub');
        $paths['core-extension'] = $paths[GeneratorName::CORE_EXTENSION];

        return $paths;
    }

    private function stubFileName(string $generator): string
    {
        return match ($generator) {
            GeneratorName::SERVICE_PROVIDER => 'service-provider.stub',
            GeneratorName::CORE_EXTENSION => 'core-extension.stub',
            default => $generator.'.stub',
        };
    }
}
