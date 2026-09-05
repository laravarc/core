<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures;

use Laravarc\Core\Contracts\SchemaIntrospector;
use Laravarc\Core\Contracts\SchemaIntrospectorFactoryContract;

final class FakeSchemaIntrospectorFactory implements SchemaIntrospectorFactoryContract
{
    public function __construct(
        private readonly SchemaIntrospector $introspector,
    ) {}

    public function forConnection(?string $connection = null): SchemaIntrospector
    {
        return $this->introspector;
    }
}
