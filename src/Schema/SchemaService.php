<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

use Laravarc\Core\Contracts\SchemaReader;
use Laravarc\Core\Module\ModuleIdentity;

final class SchemaService
{
    public function __construct(
        private readonly SchemaReader $schemaReader,
        private readonly TableNameResolver $tableNameResolver,
    ) {}

    public function resolveTableName(ModuleIdentity $identity, ?string $tableOverride = null): string
    {
        return $this->tableNameResolver->resolve($identity, $tableOverride);
    }

    public function tableExists(string $table, ?string $connection = null): bool
    {
        return $this->schemaReader->tableExists($table, $connection);
    }

    public function readSnapshot(
        ModuleIdentity $identity,
        ?string $tableOverride = null,
        ?string $connection = null,
    ): SchemaSnapshot {
        $table = $this->resolveTableName($identity, $tableOverride);

        return $this->schemaReader->read($table, $connection);
    }
}
