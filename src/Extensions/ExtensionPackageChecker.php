<?php

declare(strict_types=1);

namespace Laravarc\Core\Extensions;

use Composer\InstalledVersions;
use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Extensions\Exceptions\MissingExtensionPackageException;

final class ExtensionPackageChecker
{
    public function assertInstalled(CoreExtension $extension): void
    {
        foreach ($extension->requiredPackages() as $package) {
            if ($this->isPackageInstalled($package)) {
                continue;
            }

            throw new MissingExtensionPackageException(sprintf(
                'Extension [%s] requires the [%s] package. Run: composer require %s',
                $extension->key(),
                $package,
                $package,
            ));
        }
    }

    private function isPackageInstalled(string $package): bool
    {
        if (! class_exists(InstalledVersions::class)) {
            return false;
        }

        return InstalledVersions::isInstalled($package);
    }
}
