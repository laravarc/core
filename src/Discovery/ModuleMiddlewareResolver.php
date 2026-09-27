<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use Laravarc\Core\Contracts\ModuleMiddlewareContract;
use Laravarc\Core\Discovery\Exceptions\ModuleScanException;
use Laravarc\Core\Module\ModuleIdentity;

final class ModuleMiddlewareResolver
{
    /**
     * Discover middleware classes under Middlewares/*.php that implement the contract.
     *
     * @return list<class-string<ModuleMiddlewareContract>>
     */
    public function resolve(ModuleIdentity $identity): array
    {
        $directory = $identity->rootPath.DIRECTORY_SEPARATOR.'Middlewares';

        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        $classes = [];

        foreach ($files as $filePath) {
            $className = pathinfo($filePath, PATHINFO_FILENAME);
            $relativePath = 'Middlewares/'.$className.'.php';
            $fqcn = $identity->namespace.'\\Middlewares\\'.$className;

            if (! class_exists($fqcn)) {
                require_once $filePath;
            }

            if (! class_exists($fqcn)) {
                throw new ModuleScanException(sprintf(
                    'Module [%s] has [%s] but class [%s] could not be loaded.',
                    $identity->path,
                    $relativePath,
                    $fqcn,
                ));
            }

            if (! in_array(ModuleMiddlewareContract::class, class_implements($fqcn) ?: [], true)) {
                throw new ModuleScanException(sprintf(
                    'Module [%s] middleware [%s] must implement %s.',
                    $identity->path,
                    $fqcn,
                    ModuleMiddlewareContract::class,
                ));
            }

            $alias = $fqcn::alias();

            if ($alias === '') {
                throw new ModuleScanException(sprintf(
                    'Module [%s] middleware [%s] returned an empty alias().',
                    $identity->path,
                    $fqcn,
                ));
            }

            /** @var class-string<ModuleMiddlewareContract> $fqcn */
            $classes[] = $fqcn;
        }

        return $classes;
    }
}
