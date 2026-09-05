<?php

declare(strict_types=1);

namespace Laravarc\Core\Presentation;

use Laravarc\Core\Module\ModuleIdentity;

final readonly class PresentationGenerationContext
{
    public function __construct(
        public ModuleIdentity $identity,
        public string $moduleKey,
        public string $resourceClassName,
        public string $resourceClassShortName,
        public string $entityVariable,
        public string $collectionVariable,
    ) {}
}
