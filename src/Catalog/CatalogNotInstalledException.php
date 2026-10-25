<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use RuntimeException;

final class CatalogNotInstalledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Laravarc catalog is not installed. Run `php artisan laravarc:catalog install`.');
    }
}
