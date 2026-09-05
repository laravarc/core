<?php

declare(strict_types=1);

namespace Laravarc\Core\Presentation;

use Illuminate\Support\Str;
use Laravarc\Core\Convention\Layer;
use Laravarc\Core\Contracts\LayerResolver;
use Laravarc\Core\Contracts\ModuleKeyResolver;
use Laravarc\Core\Module\ModuleIdentity;

final class PresentationGenerationContextFactory
{
    public function __construct(
        private readonly ModuleKeyResolver $moduleKeyResolver,
        private readonly LayerResolver $layerResolver,
    ) {}

    public function make(ModuleIdentity $identity): PresentationGenerationContext
    {
        $resource = $this->layerResolver->resolve($identity, Layer::Resource);
        $shortName = class_basename($resource->className);

        return new PresentationGenerationContext(
            identity: $identity,
            moduleKey: $this->moduleKeyResolver->resolve($identity),
            resourceClassName: $resource->className,
            resourceClassShortName: $shortName,
            entityVariable: Str::camel($identity->entityName),
            collectionVariable: Str::camel(Str::plural($identity->entityName)),
        );
    }
}
