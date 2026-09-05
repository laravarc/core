<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Metadata;

final class MetadataPresetRegistry
{
    /**
     * @var array<string, list<MetadataAttribute>>
     */
    private const PRESETS = [
        'default' => [
            MetadataAttribute::Menu,
            MetadataAttribute::Feature,
            MetadataAttribute::Policy,
        ],
    ];

    public function isPreset(string $name): bool
    {
        return isset(self::PRESETS[strtolower(trim($name))]);
    }

    public function isAttribute(string $name): bool
    {
        return MetadataAttribute::tryFromName($name) !== null;
    }

    /**
     * @return list<MetadataAttribute>
     */
    public function expand(string $preset): array
    {
        $key = strtolower(trim($preset));

        if (! isset(self::PRESETS[$key])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown metadata preset [%s]. Valid presets: %s.',
                $preset,
                implode(', ', $this->presetNames()),
            ));
        }

        return self::PRESETS[$key];
    }

    /**
     * @return list<string>
     */
    public function presetNames(): array
    {
        return array_keys(self::PRESETS);
    }

    /**
     * @return list<string>
     */
    public function knownTokens(): array
    {
        return array_values(array_unique([
            ...$this->presetNames(),
            ...MetadataAttribute::names(),
        ]));
    }
}
