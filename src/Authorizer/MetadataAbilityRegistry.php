<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorizer;

use Laravarc\Authorizer\Contracts\AbilityRegistry;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Metadata\Exceptions\MetadataArtifactNotFoundException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Core-enhanced AbilityRegistry — derives keys from compiled module metadata.
 *
 * Soft-depends on laravarc/authorizer (interface). Only registered when the
 * AuthorizerCoreExtension is listed in config('laravarc.extensions').
 */
final class MetadataAbilityRegistry implements AbilityRegistry
{
    /**
     * @var array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }|null
     */
    private ?array $index = null;

    public function __construct(
        private readonly MetadataReader $metadata,
    ) {}

    public function all(): array
    {
        return $this->index()['abilities'];
    }

    public function keyFor(string $class): ?string
    {
        $index = $this->index();

        return $index['policies'][$class]
            ?? $index['models'][$class]
            ?? null;
    }

    public function has(string $policyKey, string $ability): bool
    {
        $methods = $this->all()[$policyKey] ?? null;

        return is_array($methods) && in_array($ability, $methods, true);
    }

    /**
     * @return array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }
     */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        /** @var array<string, list<string>> $abilities */
        $abilities = [];
        /** @var array<class-string, string> $policies */
        $policies = [];
        /** @var array<class-string, string> $models */
        $models = [];

        try {
            $modules = $this->metadata->artifact()->modules;
        } catch (MetadataArtifactNotFoundException) {
            $this->index = ['abilities' => [], 'policies' => [], 'models' => []];

            return $this->index;
        }

        foreach ($modules as $moduleKey => $module) {
            if (! is_array($module)) {
                continue;
            }

            $policyMeta = is_array($module['policy'] ?? null) ? $module['policy'] : [];
            $policyClass = is_string($policyMeta['policy'] ?? null) ? $policyMeta['policy'] : null;
            $modelClass = is_string($policyMeta['model'] ?? null) ? $policyMeta['model'] : null;

            /** @var list<string> $methods */
            $methods = [];

            if (is_array($policyMeta['abilities'] ?? null)) {
                foreach ($policyMeta['abilities'] as $ability) {
                    if (is_string($ability) && $ability !== '') {
                        $methods[] = $ability;
                    }
                }
            }

            if ($policyClass !== null && class_exists($policyClass) && $methods === []) {
                $methods = $this->publicMethods($policyClass);
            }

            $methods = array_values(array_unique($methods));
            sort($methods);

            if ($methods === [] && $policyClass === null) {
                continue;
            }

            $abilities[$moduleKey] = $methods;

            if ($policyClass !== null) {
                $policies[$policyClass] = $moduleKey;
            }

            if ($modelClass !== null) {
                $models[$modelClass] = $moduleKey;
            }
        }

        ksort($abilities);

        $this->index = [
            'abilities' => $abilities,
            'policies' => $policies,
            'models' => $models,
        ];

        return $this->index;
    }

    /**
     * @param  class-string  $policyClass
     * @return list<string>
     */
    private function publicMethods(string $policyClass): array
    {
        $reflection = new ReflectionClass($policyClass);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $policyClass) {
                continue;
            }

            $name = $method->getName();

            if ($name === '__construct' || str_starts_with($name, '__')) {
                continue;
            }

            $methods[] = $name;
        }

        return $methods;
    }
}
