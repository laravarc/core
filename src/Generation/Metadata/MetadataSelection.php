<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Metadata;

final readonly class MetadataSelection
{
    /**
     * @param  list<MetadataAttribute>  $attributes
     */
    private function __construct(
        public array $attributes,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromPreset(string $preset, ?MetadataPresetRegistry $registry = null): self
    {
        return new self(($registry ?? new MetadataPresetRegistry)->expand($preset));
    }

    /**
     * @param  list<MetadataAttribute>  $attributes
     */
    public static function fromAttributes(array $attributes): self
    {
        return new self(self::unique($attributes));
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }

    public function has(MetadataAttribute $attribute): bool
    {
        return in_array($attribute, $this->attributes, true);
    }

    /**
     * @param  list<MetadataAttribute>  $attributes
     * @return list<MetadataAttribute>
     */
    private static function unique(array $attributes): array
    {
        $seen = [];
        $unique = [];

        foreach ($attributes as $attribute) {
            if (isset($seen[$attribute->value])) {
                continue;
            }

            $seen[$attribute->value] = true;
            $unique[] = $attribute;
        }

        return $unique;
    }
}
