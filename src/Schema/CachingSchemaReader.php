<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

use Illuminate\Database\DatabaseManager;
use Laravarc\Core\Contracts\SchemaReader;

final class CachingSchemaReader implements SchemaReader
{
    public function __construct(
        private readonly SchemaReader $inner,
        private readonly SchemaSnapshotCache $cache,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function tableExists(string $table, ?string $connection = null): bool
    {
        return $this->inner->tableExists($table, $connection);
    }

    public function read(string $table, ?string $connection = null): SchemaSnapshot
    {
        $connectionName = $this->databaseManager->connection($connection)->getName();
        $cached = $this->cache->get($connectionName, $table);

        if ($cached !== null) {
            return $cached;
        }

        $snapshot = $this->inner->read($table, $connection);
        $this->cache->put($snapshot);

        return $snapshot;
    }
}
