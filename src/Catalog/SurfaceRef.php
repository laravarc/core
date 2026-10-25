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
}
