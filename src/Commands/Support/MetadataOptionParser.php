<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Support;

use Laravarc\Core\Generation\Metadata\MetadataAttribute;
use Laravarc\Core\Generation\Metadata\MetadataPresetRegistry;
use Laravarc\Core\Generation\Metadata\MetadataSelection;

final class MetadataOptionParser
{
    public function __construct(
        private readonly MetadataPresetRegistry $presets = new MetadataPresetRegistry,
    ) {}

    public function parse(mixed $value): MetadataSelection
    {
        if ($value === null || $value === false) {
            return MetadataSelection::empty();
        }

        if ($value === true) {
            return MetadataSelection::fromPreset('default', $this->presets);
        }

        if (! is_string($value)) {
            throw new \InvalidArgumentException('Metadata option must be a string value.');
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return MetadataSelection::fromPreset('default', $this->presets);
        }

        return MetadataSelection::fromAttributes($this->resolveTokens($trimmed));
    }

    /**
     * @return list<MetadataAttribute>
     */
    private function resolveTokens(string $value): array
    {
        $attributes = [];

        foreach (CommaSeparatedOptionParser::parse($value) as $token) {
            $normalized = strtolower($token);

            if ($this->presets->isPreset($normalized)) {
                array_push($attributes, ...$this->presets->expand($normalized));

                continue;
            }

            $attribute = MetadataAttribute::tryFromName($normalized);

            if ($attribute === null) {
                throw new \InvalidArgumentException(sprintf(
                    'Unknown metadata token [%s]. Valid values: %s.',
                    $token,
                    implode(', ', $this->presets->knownTokens()),
                ));
            }

            $attributes[] = $attribute;
        }

        return $attributes;
    }
}
