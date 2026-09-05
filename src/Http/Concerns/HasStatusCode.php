<?php

declare(strict_types=1);

namespace Laravarc\Core\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HasStatusCode
{
    protected int $statusCode = 200;

    public function withStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode($this->statusCode);
    }
}
