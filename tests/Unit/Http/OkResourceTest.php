<?php

declare(strict_types=1);

use Laravarc\Core\Http\Resources\OkResource;

it('returns an unwrapped ok payload', function () {
    expect(OkResource::ok()->resolve())->toBe(['ok' => true]);
});
