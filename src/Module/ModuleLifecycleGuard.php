<?php

declare(strict_types=1);

namespace Laravarc\Core\Module;

use Laravarc\Core\Module\Exceptions\ModuleAlreadyExistsException;
use Laravarc\Core\Module\Exceptions\ModuleNotFoundException;

final class ModuleLifecycleGuard
{
    public function assertCanMake(ModuleIdentity $identity, bool $refresh): void
    {
        if ($refresh) {
            if (! $identity->existsOnFilesystem()) {
                throw new ModuleNotFoundException(sprintf(
                    'Module not found at path "%s".',
                    $identity->path,
                ));
            }

            return;
        }

        if ($identity->existsOnFilesystem()) {
            throw new ModuleAlreadyExistsException(sprintf(
                'Module already exists at path "%s". Use --refresh to regenerate.',
                $identity->path,
            ));
        }
    }

    public function assertCanRemove(ModuleIdentity $identity): void
    {
        if (! $identity->existsOnFilesystem()) {
            throw new ModuleNotFoundException(sprintf(
                'Module not found at path "%s".',
                $identity->path,
            ));
        }
    }
}
