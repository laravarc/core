<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Illuminate\Support\Str;
use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Generation\Metadata\MetadataStubComposer;
use Laravarc\Core\Generation\StubRenderer;

final class ControllerGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::CONTROLLER;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    public function generate(GenerationContext $context): array
    {
        $template = (string) file_get_contents(
            $context->presentationStack === 'blade'
                ? $context->namedStubPath('controller_blade')
                : $context->stubPath($this->name()),
        );

        return [
            new GeneratedFile(
                relativePath: $this->relativePath($context),
                contents: StubRenderer::render($template, $this->variables($context)),
            ),
        ];
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('controller')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $controller = $context->classFor('controller');
        $model = $context->classFor('model');
        $queryService = $context->hasClass('query_service') ? $context->classFor('query_service') : null;
        $commandService = $context->hasClass('command_service') ? $context->classFor('command_service') : null;
        $service = $context->hasClass('service') ? $context->classFor('service') : null;

        $uses = [
            'use '.$model['className'].';',
        ];

        if ($queryService !== null) {
            $uses[] = 'use '.$queryService['className'].';';
        }

        if ($commandService !== null) {
            $uses[] = 'use '.$commandService['className'].';';
        }

        if ($service !== null) {
            $uses[] = 'use '.$service['className'].';';
        }

        foreach ($context->formRequestActions as $action) {
            $request = $context->classFor('form_request_'.$action);
            $uses[] = 'use '.$request['className'].';';
        }

        if ($context->presentationStack === 'api' && isset($context->resolvedClasses['resource'])) {
            $resource = $context->classFor('resource');
            $uses[] = 'use '.$resource['className'].';';
        }

        sort($uses);

        if ($context->splitServices) {
            $constructorParameters = implode(PHP_EOL, [
                '        private readonly '.$queryService['shortName'].' $'.Str::camel($queryService['shortName']).',',
                '        private readonly '.$commandService['shortName'].' $'.Str::camel($commandService['shortName']).',',
            ]);
            $indexServiceProperty = Str::camel($queryService['shortName']);
            $showServiceProperty = Str::camel($queryService['shortName']);
            $storeServiceProperty = Str::camel($commandService['shortName']);
            $updateServiceProperty = Str::camel($commandService['shortName']);
            $destroyServiceProperty = Str::camel($commandService['shortName']);
        } else {
            $constructorParameters = '        private readonly '.$service['shortName'].' $'.Str::camel($service['shortName']).',';
            $indexServiceProperty = Str::camel($service['shortName']);
            $showServiceProperty = Str::camel($service['shortName']);
            $storeServiceProperty = Str::camel($service['shortName']);
            $updateServiceProperty = Str::camel($service['shortName']);
            $destroyServiceProperty = Str::camel($service['shortName']);
        }

        $variables = [
            'namespace' => $context->moduleNamespace.'\\Controllers',
            'class' => $controller['shortName'],
            'uses' => implode(PHP_EOL, $uses),
            'constructorParameters' => $constructorParameters,
            'indexServiceProperty' => $indexServiceProperty,
            'showServiceProperty' => $showServiceProperty,
            'storeServiceProperty' => $storeServiceProperty,
            'updateServiceProperty' => $updateServiceProperty,
            'destroyServiceProperty' => $destroyServiceProperty,
            'indexMethod' => $context->splitServices ? 'paginate'.$context->moduleName : 'list',
            'showMethod' => $context->splitServices ? 'find'.$context->moduleName : 'show',
            'storeMethod' => $context->splitServices ? 'create'.$context->moduleName : 'store',
            'updateMethod' => $context->splitServices ? 'update'.$context->moduleName : 'update',
            'destroyMethod' => $context->splitServices ? 'delete'.$context->moduleName : 'destroy',
            'entityVariable' => $context->entityVariable,
            'collectionVariable' => $context->collectionVariable,
            'storeRequestClass' => $context->classFor('form_request_store')['shortName'],
            'updateRequestClass' => $context->classFor('form_request_update')['shortName'],
            'destroyRequestClass' => $context->classFor('form_request_destroy')['shortName'],
            'indexReturn' => $context->controllerReturns['index'] ?? 'response()->json([])',
            'createReturn' => $context->controllerReturns['create'] ?? "view('create')",
            'showReturn' => $context->controllerReturns['show'] ?? 'response()->json([])',
            'editReturn' => $context->controllerReturns['edit'] ?? "view('edit')",
            'storeReturn' => $context->controllerReturns['store'] ?? 'response()->json([])',
            'updateReturn' => $context->controllerReturns['update'] ?? 'response()->json([])',
            'destroyReturn' => $context->controllerReturns['destroy'] ?? 'response()->noContent()',
        ];

        return array_merge(
            $variables,
            (new MetadataStubComposer)->compose($context->metadataSelection, $context),
        );
    }
}
