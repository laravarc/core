<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog\Support;

final class CustomMenuKey
{
    private function __construct(
        private readonly string $uuid,
        private ?string $prefix = null,
        private ?string $suffix = null,
    ) {}

    public static function forUuid(string $uuid): self
    {
        return new self($uuid);
    }

    public function withPrefix(?string $prefix): self
    {
        $next = clone $this;
        $next->prefix = $prefix;

        return $next;
    }

    public function withSuffix(?string $suffix): self
    {
        $next = clone $this;
        $next->suffix = $suffix;

        return $next;
    }

    public function build(): string
    {
        $uuidSegment = str_replace('-', '_', $this->uuid);

        $segments = array_values(array_filter(
            [$this->prefix, 'custom', $uuidSegment, $this->suffix],
            static fn (?string $segment): bool => $segment !== null && $segment !== '',
        ));

        return implode('.', $segments);
    }

    /**
     * @param  callable(string): bool  $exists
     */
    public function ensureUnique(callable $exists): string
    {
        $base = $this->build();
        $key = $base;
        $n = 2;

        while ($exists($key)) {
            $key = $base.'-'.$n;
            $n++;
        }

        return $key;
    }
}
