<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Illuminate\Contracts\Events\Dispatcher;
use Laravarc\Core\Contracts\MetadataReader;
use Laravarc\Core\Metadata\Exceptions\MetadataArtifactNotFoundException;

final class CoreListenerRegistrar
{
    public function __construct(
        private readonly Dispatcher $events,
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

            foreach ($module['listeners'] ?? [] as $binding) {
                if (! is_array($binding)) {
                    continue;
                }

                $event = $binding['event'] ?? null;
                $listener = $binding['listener'] ?? null;

                if (! is_string($event) || $event === '' || ! class_exists($event)) {
                    continue;
                }

                if (! is_string($listener) || $listener === '' || ! class_exists($listener)) {
                    continue;
                }

                $this->events->listen($event, [$listener, 'handle']);
            }
        }
    }
}
