<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Mail\MailDispatcher;

final class CoreServiceProvider extends ServiceProvider
{
    /** @var list<class-string<ServiceProvider>> */
    private const PROVIDERS = [
        ConventionServiceProvider::class,
        DiscoveryServiceProvider::class,
        SchemaServiceProvider::class,
        PresentationServiceProvider::class,
        GenerationServiceProvider::class,
        CommandServiceProvider::class,
        MetadataServiceProvider::class,
        AuthorizationServiceProvider::class,
        RoutingServiceProvider::class,
        TranslationServiceProvider::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/laravarc.php', 'laravarc');

        $this->app->singleton(MailDispatcher::class);

        foreach (self::PROVIDERS as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../config/laravarc.php' => config_path('laravarc.php'),
        ], 'laravarc-config');

        $this->publishes([
            __DIR__.'/../../stubs' => resource_path('stubs/laravarc'),
        ], 'laravarc-stubs');
    }
}
