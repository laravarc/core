<?php

declare(strict_types=1);

namespace Laravarc\Core\Extensions;

final class HookClaim
{
    private function __construct(
        public readonly ExtensionHook $hook,
        public readonly HookExecution $execution,
    ) {}

    public static function exclusive(ExtensionHook $hook): self
    {
        return new self($hook, HookExecution::Exclusive);
    }

    public static function broadcast(ExtensionHook $hook): self
    {
        return new self($hook, HookExecution::Broadcast);
    }

    public static function chain(ExtensionHook $hook): self
    {
        return new self($hook, HookExecution::Chain);
    }
}
