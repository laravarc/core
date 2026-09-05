<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Laravarc\Core\Contracts\MetadataArtifactStore;
use Laravarc\Core\Contracts\MetadataCompiler;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Metadata\Exceptions\MetadataArtifactNotFoundException;

final class MetadataService implements MetadataReader
{
    public function __construct(
        private readonly MetadataArtifactStore $store,
        private readonly MetadataCompiler $compiler,
    ) {}

    public function artifact(): MetadataArtifact
    {
        if ($this->store->isPersistent()) {
            $artifact = $this->store->read();

            if ($artifact === null) {
                throw new MetadataArtifactNotFoundException(
                    'Metadata artifact not found. Run laravarc:metadata compile.',
                );
            }

            return $artifact;
        }

        return $this->compiler->compile(dryRun: true)->artifact;
    }
}
