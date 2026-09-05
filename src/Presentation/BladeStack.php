<?php

declare(strict_types=1);

namespace Laravarc\Core\Presentation;

use InvalidArgumentException;

final class BladeStack implements PresentationStack
{
    public function controllerReturn(string $action, PresentationGenerationContext $context): string
    {
        $moduleKey = $context->moduleKey;
        $entity = $context->entityVariable;
        $collection = $context->collectionVariable;

        return match ($action) {
            'index' => sprintf("view('%s::index', compact('%s'))", $moduleKey, $collection),
            'create' => sprintf("view('%s::create')", $moduleKey),
            'show' => sprintf("view('%s::show', compact('%s'))", $moduleKey, $entity),
            'edit' => sprintf("view('%s::edit', compact('%s'))", $moduleKey, $entity),
            'store' => sprintf("redirect()->route('%s.index')->with('success', 'Created successfully.')", $moduleKey),
            'update' => sprintf("redirect()->route('%s.show', %s)->with('success', 'Updated successfully.')", $moduleKey, '$'.$entity),
            'destroy' => sprintf("redirect()->route('%s.index')->with('success', 'Deleted successfully.')", $moduleKey),
            default => throw new InvalidArgumentException(sprintf('Unknown controller action "%s".', $action)),
        };
    }

    public function outputFolder(): ?string
    {
        return 'Views';
    }

    public function requiresPackage(): ?string
    {
        return null;
    }

    public static function key(): string
    {
        return 'blade';
    }
}
