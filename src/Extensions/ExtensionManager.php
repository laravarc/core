<?php

declare(strict_types=1);

namespace Laravarc\Core\Extensions;

use Illuminate\Contracts\Container\Container;
use Laravarc\Core\Contracts\CoreExtension;
use Laravarc\Core\Contracts\ModuleGenerator;
use Laravarc\Core\Extensions\Exceptions\DuplicateExtensionKeyException;
use Laravarc\Core\Extensions\Exceptions\ExclusiveHookConflictException;
use Laravarc\Core\Extensions\Exceptions\InvalidExtensionConfigurationException;

final class ExtensionManager
{
    private bool $activated = false;

    /** @var list<class-string<CoreExtension>> */
    private array $extensionClasses = [];

    /** @var array<string, list<class-string<CoreExtension>>> */
    private array $claimersByHook = [];

    /** @var array<class-string<CoreExtension>, true> */
    private array $activatedExtensions = [];

    /** @var array<string, list<string>> */
    private array $customPresets = [];

    /** @var list<ModuleGenerator> */
    private array $generators = [];

    /** @var array<string, list<callable>> */
    private array $listeners = [];

    /** @var (callable(string, string): string)|null */
    private $dispatchHandler = null;

    /** @var list<string> */
    private array $dispatchImports = [];

    public function __construct(
        private readonly Container $container,
        private readonly ExtensionPackageChecker $packageChecker,
    ) {}

    /**
     * @param  list<class-string<CoreExtension>|class-string>  $extensionClasses
     */
    public function configure(array $extensionClasses): void
    {
        $this->extensionClasses = [];
        $this->claimersByHook = [];
        $seenKeys = [];
        /** @var array<string, list<string>> $exclusiveClaimers */
        $exclusiveClaimers = [];

        foreach ($extensionClasses as $extensionClass) {
            if (! is_string($extensionClass) || $extensionClass === '') {
                throw new InvalidExtensionConfigurationException(
                    'Each Laravarc extension entry must be a non-empty class name.',
                );
            }

            if (! class_exists($extensionClass)) {
                throw new InvalidExtensionConfigurationException(sprintf(
                    'Laravarc extension class [%s] does not exist.',
                    $extensionClass,
                ));
            }

            if (! is_subclass_of($extensionClass, CoreExtension::class)) {
                throw new InvalidExtensionConfigurationException(sprintf(
                    'Laravarc extension [%s] must implement %s.',
                    $extensionClass,
                    CoreExtension::class,
                ));
            }

            /** @var CoreExtension $extension */
            $extension = $this->container->make($extensionClass);
            $key = $extension->key();

            if ($key === '') {
                throw new InvalidExtensionConfigurationException(sprintf(
                    'Laravarc extension [%s] must declare a non-empty key.',
                    $extensionClass,
                ));
            }

            if (isset($seenKeys[$key])) {
                throw new DuplicateExtensionKeyException(sprintf(
                    'Duplicate Laravarc extension key [%s].',
                    $key,
                ));
            }

            $seenKeys[$key] = true;
            $this->extensionClasses[] = $extensionClass;

            foreach ($extension->capabilities() as $claim) {
                if (! $claim instanceof HookClaim) {
                    throw new InvalidExtensionConfigurationException(sprintf(
                        'Extension [%s] capabilities() must yield %s instances.',
                        $key,
                        HookClaim::class,
                    ));
                }

                $expected = $claim->hook->executionType();

                if ($claim->execution !== $expected) {
                    throw new InvalidExtensionConfigurationException(sprintf(
                        'Extension [%s] claimed hook [%s] as [%s], but Core defines it as [%s].',
                        $key,
                        $claim->hook->name,
                        $claim->execution->name,
                        $expected->name,
                    ));
                }

                $this->claimersByHook[$claim->hook->value][] = $extensionClass;

                if ($claim->execution === HookExecution::Exclusive) {
                    $exclusiveClaimers[$claim->hook->value][] = $key;
                }
            }
        }

        foreach ($exclusiveClaimers as $hookValue => $keys) {
            $uniqueKeys = array_values(array_unique($keys));

            if (count($uniqueKeys) < 2) {
                continue;
            }

            $hook = ExtensionHook::from($hookValue);

            throw new ExclusiveHookConflictException(sprintf(
                'Hook::%s is claimed by both \'%s\' and \'%s\' as exclusive. Only one extension may claim an exclusive hook.',
                $hook->name,
                $uniqueKeys[0],
                $uniqueKeys[1],
            ));
        }
    }

    public function activate(): void
    {
        foreach ($this->extensionClasses as $extensionClass) {
            $this->activateExtension($extensionClass);
        }

        $this->activated = true;
    }

    public function activateFor(ExtensionHook $hook): void
    {
        foreach ($this->claimersByHook[$hook->value] ?? [] as $extensionClass) {
            $this->activateExtension($extensionClass);
        }

        if (($this->claimersByHook[$hook->value] ?? []) !== []) {
            $this->activated = true;
        }
    }

    public function isConfigured(): bool
    {
        return $this->extensionClasses !== [];
    }

    public function isActivated(): bool
    {
        return $this->activated;
    }

    /**
     * @return array<string, list<string>>
     */
    public function customPresets(): array
    {
        $this->activate();

        return $this->customPresets;
    }

    /**
     * @return list<ModuleGenerator>
     */
    public function generators(): array
    {
        $this->activate();

        return $this->generators;
    }

    public function dispatch(ExtensionHook $hook, mixed $payload = null): void
    {
        if (! $this->activated && ! $this->isConfigured()) {
            return;
        }

        $this->activateFor($hook);

        foreach ($this->listeners[$hook->value] ?? [] as $listener) {
            $listener($payload);
        }
    }

    /**
     * Render a generated event-dispatch statement for stubs.
     * Falls back to native event() when no extension claimed RenderDispatch.
     */
    public function renderEventDispatch(string $eventShortName, string $expr): string
    {
        $this->activateFor(ExtensionHook::RenderDispatch);

        if ($this->dispatchHandler !== null) {
            return ($this->dispatchHandler)($eventShortName, $expr);
        }

        return "event(new {$eventShortName}({$expr}));";
    }

    /**
     * @return list<string>
     */
    public function renderDispatchImports(): array
    {
        $this->activateFor(ExtensionHook::RenderDispatch);

        return $this->dispatchImports;
    }

    /**
     * @param  class-string<CoreExtension>  $extensionClass
     */
    private function activateExtension(string $extensionClass): void
    {
        if (isset($this->activatedExtensions[$extensionClass])) {
            return;
        }

        /** @var CoreExtension $extension */
        $extension = $this->container->make($extensionClass);
        $this->packageChecker->assertInstalled($extension);

        $bootstrap = new ExtensionBootstrap($this);
        $extension->register($bootstrap);

        $this->customPresets = array_merge($this->customPresets, $bootstrap->presets());
        array_push($this->generators, ...$bootstrap->generators());

        foreach ($bootstrap->listeners() as $hook => $hookListeners) {
            foreach ($hookListeners as $listener) {
                $this->listeners[$hook][] = $listener;
            }
        }

        $handler = $bootstrap->dispatchHandler();

        if ($handler !== null) {
            $this->dispatchHandler = $handler;
        }

        foreach ($bootstrap->imports() as $import) {
            if (! in_array($import, $this->dispatchImports, true)) {
                $this->dispatchImports[] = $import;
            }
        }

        $this->activatedExtensions[$extensionClass] = true;
    }
}
