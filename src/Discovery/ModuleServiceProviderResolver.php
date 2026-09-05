<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

use Illuminate\Support\Str;
use Laravarc\Core\Contracts\ModuleServiceProviderContract;
use Laravarc\Core\Discovery\Exceptions\ModuleScanException;
use Laravarc\Core\Module\ModuleIdentity;

final class ModuleServiceProviderResolver
{
    /**
     * Detect the primary module ServiceProvider ({Basename}ServiceProvider.php).
     *
     * @return list<class-string<ModuleServiceProviderContract>>
     */
    public function resolve(ModuleIdentity $identity): array
    {
        $basename = $this->basename($identity);

        if ($basename === '') {
            return [];
        }

        $className = $basename.'ServiceProvider';
        $relativePath = 'Providers/'.$className.'.php';
        $filePath = $identity->rootPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($filePath)) {
            return [];
        }

        $fqcn = $identity->namespace.'\\Providers\\'.$className;

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

        if (! in_array(ModuleServiceProviderContract::class, class_implements($fqcn) ?: [], true)) {
            throw new ModuleScanException(sprintf(
                'Module [%s] primary service provider [%s] must implement %s.',
                $identity->path,
                $fqcn,
                ModuleServiceProviderContract::class,
            ));
        }

        /** @var class-string<ModuleServiceProviderContract> $fqcn */
        return [$fqcn];
    }

    private function basename(ModuleIdentity $identity): string
    {
        $lastSegment = $identity->segments[array_key_last($identity->segments)] ?? '';

        return $lastSegment === '' ? '' : Str::studly($lastSegment);
    }
}
