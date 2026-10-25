<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

/**
 * Read-only view over the generated ability catalog (policies + gates).
 *
 * Ability keys are compound `{policy_key}.{ability}` where `policy_key` is the
 * policy FQCN in dotted snake_case. Gates are DNF: a list of AND-groups; a
 * request passes when at least one group is fully granted.
 */
interface AbilityCatalog
{
    /**
     * @return array<string, array<string, mixed>> policy_key => {class, models}
     */
    public function policies(): array;

    /**
     * @return array<string, array<string, list<list<string>>>> controller FQCN => method => DNF groups
     */
    public function gates(): array;

    /**
     * @return list<string>
     */
    public function abilitiesForPolicy(string $policyKey): array;

    /**
     * @param  class-string  $class  policy or model FQCN
     */
    public function keyForClass(string $class): ?string;

    /**
     * @return list<list<string>>|null
     */
    public function gateFor(string $controller, string $method): ?array;

    public function has(string $policyKey, string $ability): bool;
}
