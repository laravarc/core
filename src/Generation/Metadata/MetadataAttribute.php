<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Metadata;

enum MetadataAttribute: string
{
    case Menu = 'menu';
    case Feature = 'feature';
    case Policy = 'policy';
    case Public = 'public';

    public static function tryFromName(string $name): ?self
    {
        $normalized = strtolower(trim($name));

        return self::tryFrom($normalized);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $attribute): string => $attribute->value, self::cases());
    }
}
