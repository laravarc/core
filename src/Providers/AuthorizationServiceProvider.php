<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Authorization\CorePolicyRegistrar;
use Laravarc\Core\Authorization\CoreServiceRegistrar;
use Laravarc\Core\Authorization\CompiledAuthorizationRegistry;
use Laravarc\Core\Authorization\ControllerModuleResolver;
use Laravarc\Core\Authorization\GatePolicyEvaluator;
use Laravarc\Core\Authorization\PolicyTargetResolver;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Contracts\ModuleKeyResolver;
use Laravarc\Core\Contracts\PolicyEvaluator;
use Laravarc\Core\Metadata\CoreListenerRegistrar;
use Laravarc\Core\Http\Middleware\CoreAuthorizeMiddleware;
use Laravarc\Core\Metadata\MetadataService;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PolicyTargetResolver::class);
        $this->app->singleton(CompiledAuthorizationRegistry::class);
        $this->app->singleton(CorePolicyRegistrar::class);
        $this->app->singleton(CoreServiceRegistrar::class);
        $this->app->singleton(CoreListenerRegistrar::class);
        $this->app->singleton(ControllerModuleResolver::class, function ($app) {
            return new ControllerModuleResolver(
                modulesPath: (string) config('laravarc.modules_path'),
                moduleNamespace: (string) config('laravarc.module_namespace'),
                moduleKeyResolver: $app->make(ModuleKeyResolver::class),
            );
        });
        $this->app->singleton(PolicyEvaluator::class, GatePolicyEvaluator::class);
        $this->app->bind(MetadataReader::class, MetadataService::class);
    }

    public function boot(): void
    {
        $router = $this->app->make('router');
        $router->aliasMiddleware('laravarc.authorize', CoreAuthorizeMiddleware::class);

        $this->app->make(CorePolicyRegistrar::class)->register();
        $this->app->make(CoreServiceRegistrar::class)->register();
        $this->app->make(CoreListenerRegistrar::class)->register();
    }
}
