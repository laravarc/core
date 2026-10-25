<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorizer;

use Laravarc\Authorizer\Contracts\AbilityRegistry;
use Laravarc\Core\Contracts\AbilityCatalog;

/**
 * Core-enhanced AbilityRegistry — reads the generated ability catalog instead of
 * deriving keys from module metadata. Policy keys are the policy FQCN in dotted
 * snake_case, matching laravarc/authorizer >= 2.0.
 */
final class MetadataAbilityRegistry implements AbilityRegistry
{
    public function __construct(
        private readonly AbilityCatalog $catalog,
    ) {}

    public function all(): array
    {
        $abilities = [];

        foreach (array_keys($this->catalog->policies()) as $policyKey) {
            $abilities[(string) $policyKey] = $this->catalog->abilitiesForPolicy((string) $policyKey);
        }

        ksort($abilities);

        return $abilities;
    }

    public function keyFor(string $class): ?string
    {
        return $this->catalog->keyForClass($class);
    }

    public function has(string $policyKey, string $ability): bool
    {
        return $this->catalog->has($policyKey, $ability);
    }
}
