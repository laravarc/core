<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use Laravarc\Core\Contracts\AbilityCatalog;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;

final class AbilityCatalogReader implements AbilityCatalog
{
    public const SCHEMA_VERSION = 1;

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(
        private readonly string $path,
    ) {}

    public function policies(): array
    {
        return $this->data()['policies'] ?? [];
    }

    public function gates(): array
    {
        return $this->data()['gates'] ?? [];
    }

    public function abilitiesForPolicy(string $policyKey): array
    {
        $policy = $this->policies()[$policyKey] ?? null;

        if (! is_array($policy)) {
            return [];
        }

        $abilities = [];

        foreach ($policy['models'] ?? [] as $model) {
            if (! is_array($model)) {
                continue;
            }

            foreach ($model['abilities'] ?? [] as $ability) {
                if (is_string($ability) && $ability !== '') {
                    $abilities[$ability] = true;
                }
            }
        }

        $result = array_keys($abilities);
        sort($result);

        return $result;
    }

    public function keyForClass(string $class): ?string
    {
        $class = ltrim($class, '\\');

        foreach ($this->policies() as $policyKey => $policy) {
            if (! is_array($policy)) {
                continue;
            }

            if (($policy['class'] ?? null) === $class) {
                return (string) $policyKey;
            }

            foreach ($policy['models'] ?? [] as $model) {
                if (is_array($model) && ($model['class'] ?? null) === $class) {
                    return (string) $policyKey;
                }
            }
        }

        return null;
    }

    public function gateFor(string $controller, string $method): ?array
    {
        $gate = $this->gates()[$controller][$method] ?? null;

        if (! is_array($gate)) {
            return null;
        }

        return array_values(array_map(
            static fn (mixed $group): array => array_values(array_filter(
                is_array($group) ? $group : [],
                static fn (mixed $ability): bool => is_string($ability) && $ability !== '',
            )),
            $gate,
        ));
    }

    public function has(string $policyKey, string $ability): bool
    {
        return in_array($ability, $this->abilitiesForPolicy($policyKey), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        if (! is_file($this->path)) {
            throw new MetadataCompileException(sprintf(
                'Ability catalog not found at [%s]. Run laravarc:cache build.',
                $this->path,
            ));
        }

        /** @var mixed $data */
        $data = include $this->path;

        if (! is_array($data) || (int) ($data['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            throw new MetadataCompileException(sprintf(
                'Ability catalog at [%s] has an unsupported schema; run laravarc:cache build.',
                $this->path,
            ));
        }

        return $this->data = $data;
    }
}
