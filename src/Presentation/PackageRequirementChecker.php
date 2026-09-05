<?php

declare(strict_types=1);

namespace Laravarc\Core\Presentation;

use Composer\InstalledVersions;
use Laravarc\Core\Contracts\PresentationStack;
use Laravarc\Core\Presentation\Exceptions\MissingPackageRequirementException;

final class PackageRequirementChecker
{
    public function assertInstalled(PresentationStack $stack): void
    {
        $package = $stack->requiresPackage();

        if ($package === null) {
            return;
        }

        if ($this->isPackageInstalled($package)) {
            return;
        }

        throw new MissingPackageRequirementException(sprintf(
            'Presentation stack [%s] requires the [%s] package. Run: composer require %s',
            $stack::key(),
            $package,
            $package,
        ));
    }

    private function isPackageInstalled(string $package): bool
    {
        if (! class_exists(InstalledVersions::class)) {
            return false;
        }

        return InstalledVersions::isInstalled($package);
    }
}
