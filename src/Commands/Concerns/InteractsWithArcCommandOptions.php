<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Concerns;

use Illuminate\Console\Command;

/** @mixin Command */
trait InteractsWithArcCommandOptions
{
    protected function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }

    protected function isForce(): bool
    {
        return (bool) $this->option('force');
    }

    protected function metadataOptionValue(): mixed
    {
        if (! $this->input->hasParameterOption('--metadata', true)) {
            return null;
        }

        $value = $this->option('metadata');

        return $value === null ? true : $value;
    }
}
