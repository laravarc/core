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

    public static function fromCompound(string $compound): self
    {
        if ($compound === '') {
            throw new MetadataCompileException('Ability compound string must not be empty.');
        }

        $pos = strrpos($compound, '.');

        if ($pos === false) {
            throw new MetadataCompileException(sprintf(
                'Ability compound [%s] must contain a policy key and ability separated by the last dot.',
                $compound,
            ));
        }

        $policyKey = substr($compound, 0, $pos);
        $ability = substr($compound, $pos + 1);

        if ($policyKey === '' || $ability === '') {
            throw new MetadataCompileException(sprintf(
                'Ability compound [%s] must contain a policy key and ability separated by the last dot.',
                $compound,
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

    public function toCompound(): string
    {
        return $this->policyKey.'.'.$this->ability;
    }

    /**
     * @param  list<self>  $refs
     * @return list<string>
     */
    public static function toSortedCompounds(array $refs): array
    {
        $keys = [];

        foreach ($refs as $ref) {
            if ($ref instanceof self) {
                $keys[$ref->toCompound()] = true;
            }
        }

        $result = array_keys($keys);
        sort($result);

        return $result;
    }
}
