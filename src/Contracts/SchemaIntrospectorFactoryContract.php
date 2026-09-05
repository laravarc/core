<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

interface SchemaIntrospectorFactoryContract
{
    public function forConnection(?string $connection = null): SchemaIntrospector;
}
