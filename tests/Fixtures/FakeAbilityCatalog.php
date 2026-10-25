<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures;

use Laravarc\Core\Contracts\AbilityCatalog;

final class FakeAbilityCatalog implements AbilityCatalog
{
    /**
     * @param  array<string, array<string, mixed>>  $policies
     * @param  array<string, array<string, list<list<string>>>>  $gates
     */
    public function __construct(
        private readonly array $policies = [],
        private readonly array $gates = [],
    ) {}

    public function policies(): array
    {
        return $this->policies;
    }

    public function gates(): array
    {
        return $this->gates;
    }

    public function abilitiesForPolicy(string $policyKey): array
    {
        $abilities = [];

        foreach ($this->policies[$policyKey]['models'] ?? [] as $model) {
            foreach ($model['abilities'] ?? [] as $ability) {
                $abilities[$ability] = true;
            }
        }

        return array_keys($abilities);
    }

    public function keyForClass(string $class): ?string
    {
        foreach ($this->policies as $key => $policy) {
            if (($policy['class'] ?? null) === $class) {
                return (string) $key;
            }

            foreach ($policy['models'] ?? [] as $model) {
                if (($model['class'] ?? null) === $class) {
                    return (string) $key;
                }
            }
        }

        return null;
    }

    public function gateFor(string $controller, string $method): ?array
    {
        return $this->gates[$controller][$method] ?? null;
    }

    public function has(string $policyKey, string $ability): bool
    {
        return in_array($ability, $this->abilitiesForPolicy($policyKey), true);
    }
}
