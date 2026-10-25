<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

final class FakeAuthUser implements Authenticatable
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        private readonly array $abilities = [],
        private readonly bool $super = false,
    ) {}

    public function hasAbility(string $key): bool
    {
        return in_array($key, $this->abilities, true);
    }

    public function isSuper(): bool
    {
        return $this->super;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return 1;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
