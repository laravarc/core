<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Presentation\PresentationGenerationContext;

interface PresentationStack
{
    public function controllerReturn(string $action, PresentationGenerationContext $context): string;

    public function outputFolder(): ?string;

    public function requiresPackage(): ?string;

    public static function key(): string;
}
