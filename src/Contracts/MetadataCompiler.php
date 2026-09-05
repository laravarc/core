<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Metadata\MetadataCompileResult;

interface MetadataCompiler
{
    public function compile(bool $dryRun = false, ?string $modulePath = null): MetadataCompileResult;
}
