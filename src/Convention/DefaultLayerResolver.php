<?php

declare(strict_types=1);

namespace Laravarc\Core\Convention;

use Laravarc\Core\Contracts\LayerResolver as LayerResolverContract;
use Laravarc\Core\Convention\Exceptions\InvalidLayerException;
use Laravarc\Core\Module\ModuleIdentity;
use Illuminate\Support\Str;

final class DefaultLayerResolver implements LayerResolverContract
{
    public function resolve(ModuleIdentity $identity, Layer $layer, ?string $name = null): ResolvedClass
    {
        $className = $this->resolveClassName($identity, $layer, $name);
        $folder = $layer->folder();
        $relativePath = $folder.'/'.$className.'.php';

        if (str_contains($relativePath, '..')) {
            throw new InvalidLayerException('Resolved path must not escape the module root.');
        }

        return new ResolvedClass(
            className: $identity->namespace.'\\'.$folder.'\\'.$className,
            relativePath: $relativePath,
        );
    }

    private function resolveClassName(ModuleIdentity $identity, Layer $layer, ?string $name): string
    {
        return match ($layer) {
            Layer::Controller => $identity->entityName.'Controller',
            Layer::Service => $identity->entityName.'Service',
            Layer::Repository => $identity->entityName.'Repository',
            Layer::Policy => $identity->entityName.'Policy',
            Layer::Model => $identity->entityName,
            Layer::Resource => $identity->entityName.'Resource',
            Layer::Event, Layer::Listener => $this->resolveNamedClass($name, $layer),
            Layer::FormRequest => throw new InvalidLayerException(
                'Form request classes must be resolved through RequestResolver.',
            ),
        };
    }

    private function resolveNamedClass(?string $name, Layer $layer): string
    {
        if ($name === null || trim($name) === '') {
            throw new InvalidLayerException(sprintf(
                'A class name is required when resolving the %s layer.',
                $layer->value,
            ));
        }

        return Str::studly($name);
    }
}
