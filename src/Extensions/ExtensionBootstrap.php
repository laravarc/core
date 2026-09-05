<?php

declare(strict_types=1);

namespace Laravarc\Core\Extensions;

use Laravarc\Core\Contracts\ModuleGenerator;

final class ExtensionBootstrap
{
    /** @var array<string, list<string>> */
    private array $presets = [];

    /** @var list<ModuleGenerator> */
    private array $generators = [];

    /** @var array<string, list<callable>> */
    private array $listeners = [];

    /** @var (callable(string, string): string)|null */
    private $dispatchHandler = null;

    /** @var list<string> */
    private array $imports = [];

    public function __construct(
        private readonly ExtensionManager $manager,
    ) {}

    /**
     * @param  list<string>  $generators
     */
    public function addPreset(string $name, array $generators): void
    {
        $this->presets[$name] = $generators;
    }

    public function addGenerator(ModuleGenerator $generator): void
    {
        $this->generators[] = $generator;
    }

    public function listen(ExtensionHook $hook, callable $listener): void
    {
        $this->listeners[$hook->value][] = $listener;
    }

    /**
     * @param  callable(string, string): string  $handler
     */
    public function bindDispatch(callable $handler): void
    {
        $this->dispatchHandler = $handler;
    }

    public function addImport(string $fqcn): void
    {
        $this->imports[] = $fqcn;
    }

    /**
     * @return array<string, list<string>>
     */
    public function presets(): array
    {
        return $this->presets;
    }

    /**
     * @return list<ModuleGenerator>
     */
    public function generators(): array
    {
        return $this->generators;
    }

    /**
     * @return array<string, list<callable>>
     */
    public function listeners(): array
    {
        return $this->listeners;
    }

    /**
     * @return (callable(string, string): string)|null
     */
    public function dispatchHandler(): ?callable
    {
        return $this->dispatchHandler;
    }

    /**
     * @return list<string>
     */
    public function imports(): array
    {
        return $this->imports;
    }

    public function manager(): ExtensionManager
    {
        return $this->manager;
    }
}
