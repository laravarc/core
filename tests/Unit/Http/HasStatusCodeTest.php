<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravarc\Core\Http\Concerns\HasStatusCode;

final class HasStatusCodeTestResource extends JsonResource
{
    use HasStatusCode;

    public static function fromOutcome(object $outcome, int $statusCode = 200): self
    {
        return (new self($outcome))->withStatusCode($statusCode);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return ['id' => 'test'];
    }
}

it('defaults response status to 200', function () {
    $response = HasStatusCodeTestResource::fromOutcome((object) [])
        ->toResponse(Request::create('/api/test'));

    expect($response->getStatusCode())->toBe(200);
});

it('sets response status to 201 when fromOutcome receives 201', function () {
    $response = HasStatusCodeTestResource::fromOutcome((object) [], 201)
        ->toResponse(Request::create('/api/test'));

    expect($response->getStatusCode())->toBe(201);
});

it('allows chaining withStatusCode after construction', function () {
    $response = (new HasStatusCodeTestResource((object) []))
        ->withStatusCode(201)
        ->toResponse(Request::create('/api/test'));

    expect($response->getStatusCode())->toBe(201);
});
