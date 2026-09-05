<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use Illuminate\Support\Facades\Gate;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Metadata\Exceptions\MetadataArtifactNotFoundException;

final class CorePolicyRegistrar
{
    public function __construct(
        private readonly MetadataReader $metadataReader,
    ) {}

    public function register(): void
    {
        try {
            $modules = $this->metadataReader->artifact()->modules;
        } catch (MetadataArtifactNotFoundException) {
            return;
        }

        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            $policyMeta = is_array($module['policy'] ?? null) ? $module['policy'] : [];

            $this->registerBinding(
                model: $policyMeta['model'] ?? null,
                policy: $policyMeta['policy'] ?? null,
            );

            foreach ($policyMeta['ability_overrides'] ?? [] as $override) {
                if (! is_array($override)) {
                    continue;
                }

                $this->registerBinding(
                    model: $override['model'] ?? null,
                    policy: $override['policy'] ?? ($policyMeta['policy'] ?? null),
                );
            }

            foreach ($policyMeta['controllers'] ?? [] as $controller) {
                if (! is_array($controller)) {
                    continue;
                }

                $this->registerBinding(
                    model: $controller['model'] ?? null,
                    policy: $controller['policy'] ?? ($policyMeta['policy'] ?? null),
                );
            }
        }
    }

    private function registerBinding(mixed $model, mixed $policy): void
    {
        if (! is_string($model) || $model === '' || ! class_exists($model)) {
            return;
        }

        if (! is_string($policy) || $policy === '' || ! class_exists($policy)) {
            return;
        }

        Gate::policy($model, $policy);
    }
}
