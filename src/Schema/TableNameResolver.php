<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

use InvalidArgumentException;
use Laravarc\Core\Module\ModuleIdentity;

final class TableNameResolver
{
    public function resolve(ModuleIdentity $identity, ?string $tableOverride = null): string
    {
        if ($tableOverride !== null) {
            $tableOverride = trim($tableOverride);

            if ($tableOverride === '') {
                throw new InvalidArgumentException('Table override must not be empty when provided.');
            }

            $this->assertValidIdentifier($tableOverride);

            return $tableOverride;
        }

        $this->assertValidIdentifier($identity->defaultTableName);

        return $identity->defaultTableName;
    }

    private function assertValidIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Table name [%s] is not a valid database identifier.',
                $identifier,
            ));
        }
    }
}
