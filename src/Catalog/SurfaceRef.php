<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

final readonly class SurfaceRef
{
    public const KIND_MENU = 'menu';

    public const KIND_FEATURE = 'feature';

    public function __construct(
        public string $kind,
        public string $key,
    ) {}

    public static function menu(string $key): self
    {
        return new self(self::KIND_MENU, $key);
    }

    public static function feature(string $key): self
    {
        return new self(self::KIND_FEATURE, $key);
    }

    /**
     * @param  list<string>  $menuKeys
     * @return list<self>
     */
    public static function fromMenuKeys(array $menuKeys): array
    {
        $refs = [];

        foreach ($menuKeys as $key) {
            if (is_string($key) && $key !== '') {
                $refs[] = self::menu($key);
            }
        }

        return $refs;
    }

    /**
     * @param  list<string>  $featureKeys
     * @return list<self>
     */
    public static function fromFeatureKeys(array $featureKeys): array
    {
        $refs = [];

        foreach ($featureKeys as $key) {
            if (is_string($key) && $key !== '') {
                $refs[] = self::feature($key);
            }
        }

        return $refs;
    }

    /**
     * @param  list<string>  $menuKeys
     * @param  list<string>  $featureKeys
     * @return list<self>
     */
    public static function fromMenuAndFeatureKeys(array $menuKeys, array $featureKeys): array
    {
        return [
            ...self::fromMenuKeys($menuKeys),
            ...self::fromFeatureKeys($featureKeys),
        ];
    }
}
