<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Laravarc\Core\Extensions\ExtensionBootstrap;

interface CoreExtension
{
    public function key(): string;

    /**
     * @return list<string>
     */
    public function requiredPackages(): array;

    /**
     * Declarative capability claims — no logic, side effects, or I/O.
     * Yield {@see \Laravarc\Core\Extensions\HookClaim} instances only.
     * Invoked eagerly during Configure (boot).
     *
     * @return iterable<\Laravarc\Core\Extensions\HookClaim>
     */
    public function capabilities(): iterable;

    /**
     * Lazy — invoked during Activate when a claimed hook is dispatched
     * (or when presets/generators are resolved).
     */
    public function register(ExtensionBootstrap $bootstrap): void;
}
