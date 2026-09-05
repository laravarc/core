<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Services;

use Laravarc\Core\Module\ModuleIdentity;

final readonly class ModuleRelocatePlan
{
    /**
     * @param  list<string>  $filesToMove
     * @param  list<string>  $internalReplacementFiles
     * @param  list<string>  $crossModuleReplacementFiles
     */
    public function __construct(
        public ModuleIdentity $source,
        public ModuleIdentity $target,
        public array $filesToMove,
        public array $internalReplacementFiles,
        public array $crossModuleReplacementFiles,
        public ?string $routeFileRenameFrom,
        public ?string $routeFileRenameTo,
    ) {}

    public function oldNamespace(): string
    {
        return $this->source->namespace;
    }

    public function newNamespace(): string
    {
        return $this->target->namespace;
    }

    /**
     * @return list<string>
     */
    public function allReplacementFiles(): array
    {
        return array_values(array_unique([
            ...$this->internalReplacementFiles,
            ...$this->crossModuleReplacementFiles,
        ]));
    }
}
