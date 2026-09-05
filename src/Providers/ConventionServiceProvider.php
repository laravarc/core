<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Contracts\LayerResolver;
use Laravarc\Core\Contracts\ModuleKeyResolver;
use Laravarc\Core\Contracts\RequestResolver;
use Laravarc\Core\Providers\Concerns\RegistersConventionBindings;

final class ConventionServiceProvider extends ServiceProvider
{
    use RegistersConventionBindings;

    public function register(): void
    {
        $this->registerConventionBinding($this->app, 'laravarc.convention.layer_resolver', LayerResolver::class);
        $this->registerConventionBinding($this->app, 'laravarc.convention.module_key_resolver', ModuleKeyResolver::class);
        $this->registerConventionBinding($this->app, 'laravarc.convention.request_resolver', RequestResolver::class);
    }
}
