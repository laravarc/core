<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;

final readonly class AbilityRef
{
    public function __construct(
        public string $policyKey,
        public string $ability,
    ) {}

    public static function fromAbilityKey(string $abilityKey): self
    {
        if ($abilityKey === '') {
            throw new MetadataCompileException('Ability key must not be empty.');
        }

        $pos = strrpos($abilityKey, '.');

        if ($pos === false) {
            throw new MetadataCompileException(sprintf(
                'Ability key [%s] must contain a policy key and ability separated by the last dot.',
                $abilityKey,
            ));
        }

        $policyKey = substr($abilityKey, 0, $pos);
        $ability = substr($abilityKey, $pos + 1);

        if ($policyKey === '' || $ability === '') {
            throw new MetadataCompileException(sprintf(
                'Ability key [%s] must contain a policy key and ability separated by the last dot.',
                $abilityKey,
            ));
        }

        return new self($policyKey, $ability);
    }

    /**
     * @return array{policy_key: string, ability: string}
     */
    public function toArray(): array
    {
        return [
            'policy_key' => $this->policyKey,
            'ability' => $this->ability,
        ];
    }

    /**
     * @param  array{policy_key: string, ability: string}  $row
     */
    public static function fromStored(array $row): self
    {
        return new self((string) $row['policy_key'], (string) $row['ability']);
    }

    public function toAbilityKey(): string
    {
        return $this->policyKey.'.'.$this->ability;
    }

    /**
     * @param  list<self>  $refs
     * @return list<string>
     */
    public static function sortedAbilityKeys(array $refs): array
    {
        $keys = [];

        foreach ($refs as $ref) {
            if ($ref instanceof self) {
                $keys[$ref->toAbilityKey()] = true;
            }
        }

        $result = array_keys($keys);
        sort($result);

        return $result;
    }
}
