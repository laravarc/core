<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Schema\SchemaSnapshot;

interface SchemaReader
{
    public function tableExists(string $table, ?string $connection = null): bool;

    public function read(string $table, ?string $connection = null): SchemaSnapshot;
}
