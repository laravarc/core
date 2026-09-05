<?php

declare(strict_types=1);

namespace Laravarc\Core\Convention;

use Laravarc\Core\Contracts\RequestResolver as RequestResolverContract;
use Laravarc\Core\Convention\Exceptions\InvalidLayerException;
use Laravarc\Core\Module\ModuleIdentity;
use Illuminate\Support\Str;

final class DefaultRequestResolver implements RequestResolverContract
{
    public function resolve(ModuleIdentity $identity, string $action): ResolvedClass
    {
        $action = trim($action);

        if ($action === '') {
            throw new InvalidLayerException('Request action must not be empty.');
        }

        $className = Str::studly($action).$identity->entityName.'Request';
        $folder = Layer::FormRequest->folder();
        $relativePath = $folder.'/'.$className.'.php';

        return new ResolvedClass(
            className: $identity->namespace.'\\'.$folder.'\\'.$className,
            relativePath: $relativePath,
        );
    }
}
