<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

use Laravarc\Core\Extensions\ExtensionManager;

final class ModulePresetRegistry
{
    public const PRESET_CRUD = 'crud';

    public const PRESET_CRUD_RESOURCE = 'crud+resource';

    public const PRESET_CRUD_SEED = 'crud+seed';

    public const PRESET_CRUD_EVENTS = 'crud+events';

    public const PRESET_FULL = 'full';

    public const PRESET_CRUD_METADATA = 'crud+metadata';

    /**
     * @param  array<string, list<string>>  $customPresets
     */
    public function __construct(
        private readonly array $customPresets = [],
        private readonly ?ExtensionManager $extensions = null,
    ) {}

    /**
     * @return list<string>
     */
    public function generatorsFor(string $preset): array
    {
        $presets = $this->allPresets();

        if (! isset($presets[$preset])) {
            throw new Exceptions\UnknownPresetException(sprintf(
                'Unknown preset [%s]. Valid presets: %s.',
                $preset,
                implode(', ', array_keys($presets)),
            ));
        }

        return $presets[$preset];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->allPresets());
    }

    public function exists(string $preset): bool
    {
        return isset($this->allPresets()[$preset]);
    }

    public function enablesMetadata(string $preset): bool
    {
        return $preset === self::PRESET_CRUD_METADATA;
    }

    public function normalizePreset(string $preset): string
    {
        return $this->enablesMetadata($preset) ? self::PRESET_CRUD : $preset;
    }

    /**
     * @return array<string, list<string>>
     */
    private function allPresets(): array
    {
        $crud = [
            GeneratorName::MIGRATION,
            GeneratorName::MODEL,
            GeneratorName::REPOSITORY,
            GeneratorName::SERVICE,
            GeneratorName::CONTROLLER,
            GeneratorName::FORM_REQUEST,
            GeneratorName::POLICY,
            GeneratorName::VIEW,
            GeneratorName::ROUTE,
            GeneratorName::SERVICE_PROVIDER,
        ];

        return array_merge([
            self::PRESET_CRUD => $crud,
            self::PRESET_CRUD_METADATA => $crud,
            self::PRESET_CRUD_RESOURCE => [...$crud, GeneratorName::RESOURCE],
            self::PRESET_CRUD_SEED => [...$crud, GeneratorName::SEEDER],
            self::PRESET_CRUD_EVENTS => [...$crud, GeneratorName::EVENT, GeneratorName::LISTENER],
            self::PRESET_FULL => GeneratorName::all(),
        ], $this->customPresets, $this->extensions?->customPresets() ?? []);
    }
}
