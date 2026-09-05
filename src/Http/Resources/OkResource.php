<?php

declare(strict_types=1);

namespace Laravarc\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read array{ok: bool} $resource */
final class OkResource extends JsonResource
{
    public static $wrap = null;

    public static function ok(): self
    {
        return new self(['ok' => true]);
    }

    /**
     * @return array{ok: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'ok' => true,
        ];
    }
}
