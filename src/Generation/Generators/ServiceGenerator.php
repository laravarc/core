<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Generation\StubRenderer;

final class ServiceGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function __construct(
        private readonly ?ExtensionManager $extensions = null,
    ) {}

    public function name(): string
    {
        return GeneratorName::SERVICE;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    public function generate(GenerationContext $context): array
    {
        if (! $context->splitServices) {
            return parent::generate($context);
        }

        $repository = $context->classFor('repository');
        $model = $context->classFor('model');
        $commandService = $context->classFor('command_service');
        $queryService = $context->classFor('query_service');
        $uses = [
            'use '.$model['className'].';',
            'use '.$repository['className'].';',
        ];

        $commandTemplate = (string) file_get_contents($context->namedStubPath('command_service'));
        $queryTemplate = (string) file_get_contents($context->namedStubPath('query_service'));

        $attributeNamespace = 'Laravarc\\Core\\Metadata\\Attributes';
        $commandAttributeUse = $context->withContractAttributes
            ? 'use '.$attributeNamespace.'\\CommandContract;'
            : '';
        $queryAttributeUse = $context->withContractAttributes
            ? 'use '.$attributeNamespace.'\\QueryContract;'
            : '';
        $commandAttribute = $context->withContractAttributes ? '#[CommandContract]' : '';
        $queryAttribute = $context->withContractAttributes ? '#[QueryContract]' : '';
        $restoreMethod = '';

        if ($context->schemaSnapshot?->softDeletes) {
            $restoreMethod = StubRenderer::render(
                <<<'PHP'

    {{ commandAttribute }}
    public function restore{{ moduleName }}(mixed $id): {{ modelShortName }}
    {
        return $this->repository->restore($id);
    }
PHP,
                [
                    'commandAttribute' => $commandAttribute,
                    'moduleName' => $context->moduleName,
                    'modelShortName' => $model['shortName'],
                ],
            );
        }

        $createdEventDispatch = '';
        $deletedEventDispatch = '';

        if ($context->withEvents) {
            $createdEvent = $context->classFor('event');
            $deletedEvent = $context->classFor('event_deleted');
            $uses[] = 'use '.$createdEvent['className'].';';
            $uses[] = 'use '.$deletedEvent['className'].';';

            $createdExpr = '(int) $'.$context->entityVariable.'->getKey()';
            $deletedExpr = '(int) $id';

            if ($this->extensions !== null) {
                foreach ($this->extensions->renderDispatchImports() as $import) {
                    $uses[] = 'use '.$import.';';
                }

                $createdEventDispatch = '        '.$this->extensions->renderEventDispatch(
                    $createdEvent['shortName'],
                    $createdExpr,
                );
                $deletedEventDispatch = '        '.$this->extensions->renderEventDispatch(
                    $deletedEvent['shortName'],
                    $deletedExpr,
                );
            } else {
                $createdEventDispatch = '        event(new '.$createdEvent['shortName'].'('.$createdExpr.'));';
                $deletedEventDispatch = '        event(new '.$deletedEvent['shortName'].'('.$deletedExpr.'));';
            }
        }

        $variables = [
            'moduleName' => $context->moduleName,
            'namespace' => $context->moduleNamespace,
            'modelClass' => $model['className'],
            'modelShortName' => $model['shortName'],
            'repositoryClass' => $repository['className'],
            'repositoryShortName' => $repository['shortName'],
            'entityVariable' => $context->entityVariable,
            'uses' => implode(PHP_EOL, $uses),
            'commandAttributeUse' => $commandAttributeUse,
            'queryAttributeUse' => $queryAttributeUse,
            'commandAttribute' => $commandAttribute,
            'queryAttribute' => $queryAttribute,
            'restoreMethod' => $restoreMethod,
            'createdEventDispatch' => $createdEventDispatch === '' ? '' : PHP_EOL.$createdEventDispatch,
            'deletedEventDispatch' => $deletedEventDispatch === '' ? '' : PHP_EOL.$deletedEventDispatch,
        ];

        return [
            new GeneratedFile(
                relativePath: $commandService['relativePath'],
                contents: StubRenderer::render($commandTemplate, $variables),
            ),
            new GeneratedFile(
                relativePath: $queryService['relativePath'],
                contents: StubRenderer::render($queryTemplate, $variables),
            ),
        ];
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('service')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $service = $context->classFor('service');
        $repository = $context->classFor('repository');
        $model = $context->classFor('model');

        return [
            'namespace' => $context->moduleNamespace.'\\Services',
            'class' => $service['shortName'],
            'repositoryClass' => $repository['className'],
            'repositoryShortName' => $repository['shortName'],
            'modelClass' => $model['className'],
            'modelShortName' => $model['shortName'],
            'entityVariable' => $context->entityVariable,
            'collectionVariable' => $context->collectionVariable,
        ];
    }
}
