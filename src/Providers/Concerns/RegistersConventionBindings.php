<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers\Concerns;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

trait RegistersConventionBindings
{
    /**
     * @param  class-string  $contract
     */
    protected function registerConventionBinding(Application $app, string $configKey, string $contract): void
    {
        $app->singleton($contract, function ($app) use ($configKey, $contract) {
            $implementation = config($configKey);

            if (! is_string($implementation) || $implementation === '') {
                throw new InvalidArgumentException(sprintf(
                    'Convention binding [%s] must be configured with a valid class name.',
                    $configKey,
                ));
            }

            if (! is_subclass_of($implementation, $contract)) {
                throw new InvalidArgumentException(sprintf(
                    'Convention binding [%s] must implement %s.',
                    $configKey,
                    $contract,
                ));
            }

            return $app->make($implementation);
        });
    }
}
