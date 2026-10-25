<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Illuminate\Contracts\Events\Dispatcher;
use Laravarc\Core\Discovery\ModuleRegistry;

final class CoreListenerRegistrar
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly ModuleRegistry $moduleRegistry,
    ) {}

    public function register(): void
    {
        foreach ($this->moduleRegistry->all() as $entry) {
            foreach ($entry->listeners as $binding) {
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
