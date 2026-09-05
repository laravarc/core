<?php

declare(strict_types=1);

namespace Laravarc\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Laravarc\Core\Presentation\PackageRequirementChecker;
use Laravarc\Core\Presentation\PresentationGenerationContextFactory;
use Laravarc\Core\Presentation\PresentationStackRegistry;

final class PresentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PresentationGenerationContextFactory::class);
        $this->app->singleton(PackageRequirementChecker::class);

        $this->app->singleton(PresentationStackRegistry::class, function ($app) {
            /** @var list<class-string> $stackClasses */
            $stackClasses = config('laravarc.stacks', []);

            return PresentationStackRegistry::fromConfig(
                $stackClasses,
                (string) config('laravarc.default_stack', 'api'),
                $app,
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(PresentationStackRegistry::class);
    }
}
