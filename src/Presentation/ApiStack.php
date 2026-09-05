<?php

declare(strict_types=1);

namespace Laravarc\Core\Presentation;

use InvalidArgumentException;
use Laravarc\Core\Contracts\PresentationStack as PresentationStackContract;

final class ApiStack implements PresentationStack
{
    public function controllerReturn(string $action, PresentationGenerationContext $context): string
    {
        $resource = $context->resourceClassShortName;
        $entity = '$'.$context->entityVariable;
        $collection = '$'.$context->collectionVariable;

        return match ($action) {
            'index' => sprintf('%s::collection(%s)', $resource, $collection),
            'show' => sprintf('new %s(%s)', $resource, $entity),
            'store' => sprintf('%s::fromOutcome(%s, 201)', $resource, $entity),
            'update' => sprintf('new %s(%s)', $resource, $entity),
            'destroy' => 'response()->noContent()',
            default => throw new InvalidArgumentException(sprintf('Unknown controller action "%s".', $action)),
        };
    }

    public function outputFolder(): ?string
    {
        return 'Resources';
    }

    public function requiresPackage(): ?string
    {
        return null;
    }

    public static function key(): string
    {
        return 'api';
    }
}
