<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Laravarc\Core\Extensions\Exceptions\DuplicateExtensionKeyException;
use Laravarc\Core\Extensions\Exceptions\ExclusiveHookConflictException;
use Laravarc\Core\Extensions\Exceptions\InvalidExtensionConfigurationException;
use Laravarc\Core\Extensions\Exceptions\MissingExtensionPackageException;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\ExtensionManager;
use Laravarc\Core\Extensions\ExtensionPackageChecker;
use Laravarc\Core\Generation\ModulePresetRegistry;
use Laravarc\Core\Tests\Fixtures\Extensions\FakeBroadcastGenerationBeforeAExtension;
use Laravarc\Core\Tests\Fixtures\Extensions\FakeBroadcastGenerationBeforeBExtension;
use Laravarc\Core\Tests\Fixtures\Extensions\FakeDuplicateKeyExtension;
use Laravarc\Core\Tests\Fixtures\Extensions\FakeExclusiveRenderDispatchAExtension;
use Laravarc\Core\Tests\Fixtures\Extensions\FakeExclusiveRenderDispatchBExtension;
use Laravarc\Core\Tests\Fixtures\Extensions\FakeHookExtension;
use Laravarc\Core\Tests\Fixtures\Extensions\FakeMismatchedClaimExtension;
use Laravarc\Core\Tests\Fixtures\Extensions\FakeMissingPackageExtension;
use Laravarc\Core\Tests\Fixtures\Extensions\FakePresetExtension;
use Laravarc\Eventer\EventerExtension;

function extensionManager(array $classes = []): ExtensionManager
{
    $container = new Container;
    $manager = new ExtensionManager(
        container: $container,
        packageChecker: new ExtensionPackageChecker,
    );

    foreach ($classes as $class) {
        $container->singleton($class, fn (): object => new $class);
    }

    $manager->configure($classes);

    return $manager;
}

describe('ExtensionManager', function () {
    it('accepts empty extension configuration', function () {
        $manager = extensionManager();

        expect($manager->isConfigured())->toBeFalse()
            ->and($manager->isActivated())->toBeFalse();
    });

    it('rejects invalid extension classes at configuration', function () {
        expect(fn () => extensionManager(['NotAClass']))
            ->toThrow(InvalidExtensionConfigurationException::class);
    });

    it('rejects duplicate extension keys at configuration', function () {
        expect(fn () => extensionManager([
            FakeHookExtension::class,
            FakeDuplicateKeyExtension::class,
        ]))->toThrow(DuplicateExtensionKeyException::class);
    });

    it('rejects mismatched hook execution claims at configuration', function () {
        expect(fn () => extensionManager([FakeMismatchedClaimExtension::class]))
            ->toThrow(
                InvalidExtensionConfigurationException::class,
                "claimed hook [GenerationBefore] as [Exclusive], but Core defines it as [Broadcast]",
            );
    });

    it('rejects exclusive hook conflicts at configuration before any activation', function () {
        expect(fn () => extensionManager([
            FakeExclusiveRenderDispatchAExtension::class,
            FakeExclusiveRenderDispatchBExtension::class,
        ]))->toThrow(
            ExclusiveHookConflictException::class,
            "Hook::RenderDispatch is claimed by both 'eventer' and 'queue-thing' as exclusive",
        );
    });

    it('allows multiple extensions to claim the same broadcast hook', function () {
        FakeBroadcastGenerationBeforeAExtension::reset();

        $manager = extensionManager([
            FakeBroadcastGenerationBeforeAExtension::class,
            FakeBroadcastGenerationBeforeBExtension::class,
        ]);

        $manager->dispatch(ExtensionHook::GenerationBefore);

        expect(FakeBroadcastGenerationBeforeAExtension::$activated)
            ->toBe(['fake-broadcast-a', 'fake-broadcast-b']);
    });

    it('ignores installed packages that are not listed in config extensions', function () {
        expect(class_exists(EventerExtension::class))->toBeTrue();

        $manager = extensionManager();

        expect($manager->isConfigured())->toBeFalse()
            ->and($manager->renderEventDispatch('UserCreatedEvent', '(int) $id'))
            ->toBe('event(new UserCreatedEvent((int) $id));')
            ->and($manager->renderDispatchImports())->toBe([]);
    });

    it('activates extensions lazily and dispatches hooks in registration order', function () {
        FakeHookExtension::reset();

        $manager = extensionManager([FakeHookExtension::class]);

        expect($manager->isActivated())->toBeFalse();

        $manager->dispatch(ExtensionHook::GenerationBefore);

        expect($manager->isActivated())->toBeTrue()
            ->and(FakeHookExtension::$hooks)->toBe(['generation:before']);
    });

    it('fails activation when required package is missing', function () {
        $manager = extensionManager([FakeMissingPackageExtension::class]);

        expect(fn () => $manager->dispatch(ExtensionHook::CacheRefresh))
            ->toThrow(MissingExtensionPackageException::class, 'vendor/nonexistent-package');
    });

    it('registers custom presets through extensions', function () {
        $manager = extensionManager([FakePresetExtension::class]);
        $registry = new ModulePresetRegistry(extensions: $manager);

        expect($registry->exists('fake-preset'))->toBeTrue()
            ->and($registry->generatorsFor('fake-preset'))->toBe(['custom-generator']);
    });
});

describe('ExtensionManager container binding', function () {
    it('registers with empty extensions config by default', function () {
        $manager = app(ExtensionManager::class);

        expect($manager->isConfigured())->toBeFalse();
    });
});
