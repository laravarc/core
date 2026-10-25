<?php

declare(strict_types=1);

use Laravarc\Core\Catalog\Support\CustomMenuKey;

describe('CustomMenuKey', function () {
    it('builds user custom keys from uuid with underscores', function () {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        expect(CustomMenuKey::forUuid($uuid)->build())
            ->toBe('custom.550e8400_e29b_41d4_a716_446655440000');
    });

    it('builds admin-prefixed keys and optional affixes', function () {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $segment = '550e8400_e29b_41d4_a716_446655440000';

        expect(CustomMenuKey::forUuid($uuid)->withPrefix('admin')->build())
            ->toBe('admin.custom.'.$segment)
            ->and(CustomMenuKey::forUuid($uuid)->withPrefix('admin')->withSuffix('beta')->build())
            ->toBe('admin.custom.'.$segment.'.beta');
    });

    it('appends numeric suffixes when the key already exists', function () {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $base = CustomMenuKey::forUuid($uuid)->build();
        $seen = [$base => true];

        $key = CustomMenuKey::forUuid($uuid)->ensureUnique(
            static fn (string $k): bool => isset($seen[$k]),
        );

        expect($key)->toBe($base.'-2');
    });
});
