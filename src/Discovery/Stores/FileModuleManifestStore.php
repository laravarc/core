<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery\Stores;

use Laravarc\Core\Discovery\Exceptions\CorruptModuleManifestException;
use Laravarc\Core\Discovery\ModuleManifest;

final class FileModuleManifestStore extends AbstractFileModuleManifestStore
{
    public function read(): ?ModuleManifest
    {
        if (! is_file($this->path)) {
            return null;
        }

        try {
            /** @var mixed $data */
            $data = include $this->path;

            if (! is_array($data)) {
                throw new \InvalidArgumentException('Manifest file must return an array.');
            }

            return ModuleManifest::fromArray($data);
        } catch (\Throwable $exception) {
            throw new CorruptModuleManifestException(
                sprintf('Module manifest at [%s] is corrupt. Run laravarc:cache refresh.', $this->path),
                previous: $exception,
            );
        }
    }

    protected function encode(ModuleManifest $manifest): string
    {
        $exported = var_export($manifest->toArray(), true);

        return <<<PHP
<?php

declare(strict_types=1);

return {$exported};

PHP;
    }

    protected function decode(string $contents): ModuleManifest
    {
        throw new \LogicException('FileModuleManifestStore reads manifests via include(), not decode().');
    }
}
