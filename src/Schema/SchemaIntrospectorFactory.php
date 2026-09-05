<?php

declare(strict_types=1);

namespace Laravarc\Core\Schema;

use Illuminate\Database\DatabaseManager;
use Laravarc\Core\Contracts\SchemaIntrospector;
use Laravarc\Core\Contracts\SchemaIntrospectorFactoryContract;

final class SchemaIntrospectorFactory implements SchemaIntrospectorFactoryContract
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function forConnection(?string $connection = null): SchemaIntrospector
    {
        return new ConnectionSchemaIntrospector(
            $this->databaseManager->connection($connection),
        );
    }
}
