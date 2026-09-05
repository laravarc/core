<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Laravarc\Core\Discovery\ModuleManifestEntry;
use Laravarc\Core\Metadata\Attributes\ListenTo;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;
use Laravarc\Core\Module\ModuleLayout;
use ReflectionClass;

final class ListenerMetadataReader
{
    public function __construct(
        private readonly ModuleClassDiscoverer $classDiscoverer,
    ) {}

    /**
     * @return list<array{event: string, listener: string}>
     */
    public function readModule(ModuleManifestEntry $entry): array
    {
        $listenersPath = $entry->rootPath.'/'.ModuleLayout::LISTENERS;

        if (! is_dir($listenersPath)) {
            return [];
        }

        $bindings = [];
        $listenerNamespace = $entry->namespace.'\\'.ModuleLayout::LISTENERS;

        foreach ($this->classDiscoverer->discover($listenersPath, $listenerNamespace) as $listenerClass) {
            $bindings = array_merge($bindings, $this->readListenerClass($listenerClass));
        }

        return $bindings;
    }

    public function hasListenerSignals(ModuleManifestEntry $entry): bool
    {
        return $this->readModule($entry) !== [];
    }

    /**
     * @return list<array{event: string, listener: string}>
     */
    private function readListenerClass(string $listenerClass): array
    {
        $reflection = new ReflectionClass($listenerClass);

        if (! $reflection->hasMethod('handle')) {
            throw new MetadataCompileException(sprintf(
                'Listener [%s] must declare a handle() method.',
                $listenerClass,
            ));
        }

        $bindings = [];

        foreach ($reflection->getAttributes(ListenTo::class) as $attribute) {
            /** @var ListenTo $listenTo */
            $listenTo = $attribute->newInstance();
            $eventClass = $listenTo->event;

            if (! class_exists($eventClass)) {
                throw new MetadataCompileException(sprintf(
                    'Listener [%s] references unknown event class [%s].',
                    $listenerClass,
                    $eventClass,
                ));
            }

            $bindings[] = [
                'event' => $eventClass,
                'listener' => $listenerClass,
            ];
        }

        return $bindings;
    }
}
