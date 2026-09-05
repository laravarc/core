<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Laravarc\Core\Contracts\PresentationStack as PresentationStackContract;
use Laravarc\Core\Convention\DefaultLayerResolver;
use Laravarc\Core\Convention\DefaultModuleKeyResolver;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Presentation\ApiStack;
use Laravarc\Core\Presentation\BladeStack;
use Laravarc\Core\Presentation\Exceptions\MissingPackageRequirementException;
use Laravarc\Core\Presentation\Exceptions\UnknownPresentationStackException;
use Laravarc\Core\Presentation\PackageRequirementChecker;
use Laravarc\Core\Presentation\PresentationGenerationContext;
use Laravarc\Core\Presentation\PresentationGenerationContextFactory;
use Laravarc\Core\Presentation\PresentationStackRegistry;

function presentationTestContext(): PresentationGenerationContext
{
    $modulesPath = sys_get_temp_dir().'/arc-presentation-'.uniqid('', true);
    mkdir($modulesPath, 0777, true);

    $identity = ModuleIdentity::fromPath('admin/user', $modulesPath, 'App\\Modules');

    return new PresentationGenerationContext(
        identity: $identity,
        moduleKey: 'admin.user',
        resourceClassName: 'App\\Modules\\Admin\\User\\Resources\\UserResource',
        resourceClassShortName: 'UserResource',
        entityVariable: 'user',
        collectionVariable: 'users',
    );
}

describe('PresentationGenerationContextFactory', function () {
    it('builds context from module identity using convention resolvers', function () {
        $modulesPath = sys_get_temp_dir().'/arc-presentation-factory-'.uniqid('', true);
        mkdir($modulesPath, 0777, true);

        $identity = ModuleIdentity::fromPath('admin/user', $modulesPath, 'App\\Modules');
        $factory = new PresentationGenerationContextFactory(
            new DefaultModuleKeyResolver,
            new DefaultLayerResolver,
        );

        $context = $factory->make($identity);

        expect($context->moduleKey)->toBe('admin.user')
            ->and($context->resourceClassName)->toBe('App\\Modules\\Admin\\User\\Resources\\UserResource')
            ->and($context->resourceClassShortName)->toBe('UserResource')
            ->and($context->entityVariable)->toBe('user')
            ->and($context->collectionVariable)->toBe('users');
    });
});

describe('ApiStack', function () {
    beforeEach(function () {
        $this->stack = new ApiStack;
        $this->context = presentationTestContext();
    });

    it('returns JsonResource collection for index', function () {
        expect($this->stack->controllerReturn('index', $this->context))
            ->toBe('UserResource::collection($users)');
    });

    it('returns JsonResource instance for show and update', function () {
        expect($this->stack->controllerReturn('show', $this->context))
            ->toBe('new UserResource($user)')
            ->and($this->stack->controllerReturn('update', $this->context))
            ->toBe('new UserResource($user)');
    });

    it('returns 201 JsonResource response for store', function () {
        expect($this->stack->controllerReturn('store', $this->context))
            ->toBe('UserResource::fromOutcome($user, 201)');
    });

    it('returns no content response for destroy', function () {
        expect($this->stack->controllerReturn('destroy', $this->context))
            ->toBe('response()->noContent()');
    });

    it('exposes Resources output folder and no package requirement', function () {
        expect($this->stack->outputFolder())->toBe('Resources')
            ->and($this->stack->requiresPackage())->toBeNull()
            ->and(ApiStack::key())->toBe('api');
    });

    it('rejects unknown actions', function () {
        expect(fn () => $this->stack->controllerReturn('unknown', $this->context))
            ->toThrow(\InvalidArgumentException::class, 'Unknown controller action');
    });
});

describe('BladeStack', function () {
    beforeEach(function () {
        $this->stack = new BladeStack;
        $this->context = presentationTestContext();
    });

    it('returns namespaced module view names for index, create, show, and edit', function () {
        expect($this->stack->controllerReturn('index', $this->context))
            ->toBe("view('admin.user::index', compact('users'))")
            ->and($this->stack->controllerReturn('create', $this->context))
            ->toBe("view('admin.user::create')")
            ->and($this->stack->controllerReturn('show', $this->context))
            ->toBe("view('admin.user::show', compact('user'))")
            ->and($this->stack->controllerReturn('edit', $this->context))
            ->toBe("view('admin.user::edit', compact('user'))");
    });

    it('returns redirect responses with flash for mutating actions', function () {
        expect($this->stack->controllerReturn('store', $this->context))
            ->toBe("redirect()->route('admin.user.index')->with('success', 'Created successfully.')")
            ->and($this->stack->controllerReturn('update', $this->context))
            ->toBe("redirect()->route('admin.user.show', \$user)->with('success', 'Updated successfully.')")
            ->and($this->stack->controllerReturn('destroy', $this->context))
            ->toBe("redirect()->route('admin.user.index')->with('success', 'Deleted successfully.')");
    });

    it('defines Views as the output folder', function () {
        expect($this->stack->outputFolder())->toBe('Views')
            ->and(BladeStack::key())->toBe('blade');
    });
});

describe('PresentationStackRegistry', function () {
    it('resolves default and explicit stack keys', function () {
        $container = new Application;
        $container->instance(ApiStack::class, new ApiStack);
        $container->instance(BladeStack::class, new BladeStack);

        $registry = PresentationStackRegistry::fromConfig(
            [ApiStack::class, BladeStack::class],
            'api',
            $container,
        );

        expect($registry->resolve())->toBeInstanceOf(ApiStack::class)
            ->and($registry->resolve('blade'))->toBeInstanceOf(BladeStack::class)
            ->and($registry->keys())->toBe(['api', 'blade'])
            ->and($registry->defaultKey())->toBe('api');
    });

    it('rejects unknown stack keys', function () {
        $container = new Application;
        $container->instance(ApiStack::class, new ApiStack);

        $registry = PresentationStackRegistry::fromConfig([ApiStack::class], 'api', $container);

        expect(fn () => $registry->resolve('inertia'))
            ->toThrow(UnknownPresentationStackException::class, 'Unknown presentation stack [inertia]');
    });

    it('rejects duplicate stack keys at construction', function () {
        $first = new class implements PresentationStackContract
        {
            public function controllerReturn(string $action, PresentationGenerationContext $context): string
            {
                return '';
            }

            public function outputFolder(): ?string
            {
                return null;
            }

            public function requiresPackage(): ?string
            {
                return null;
            }

            public static function key(): string
            {
                return 'api';
            }
        };

        $duplicate = new class implements PresentationStackContract
        {
            public function controllerReturn(string $action, PresentationGenerationContext $context): string
            {
                return '';
            }

            public function outputFolder(): ?string
            {
                return null;
            }

            public function requiresPackage(): ?string
            {
                return null;
            }

            public static function key(): string
            {
                return 'api';
            }
        };

        $container = new Application;
        $container->instance($first::class, $first);
        $container->instance($duplicate::class, $duplicate);

        expect(fn () => PresentationStackRegistry::fromConfig(
            [$first::class, $duplicate::class],
            'api',
            $container,
        ))->toThrow(\InvalidArgumentException::class, 'Duplicate presentation stack key [api]');
    });

    it('rejects invalid stack classes at construction', function () {
        $container = new Application;

        expect(fn () => PresentationStackRegistry::fromConfig(
            ['stdClass'],
            'api',
            $container,
        ))->toThrow(\InvalidArgumentException::class, 'must implement');
    });

    it('rejects default stack mismatch', function () {
        $container = new Application;
        $container->instance(ApiStack::class, new ApiStack);

        expect(fn () => PresentationStackRegistry::fromConfig(
            [ApiStack::class],
            'blade',
            $container,
        ))->toThrow(\InvalidArgumentException::class, 'Default presentation stack [blade]');
    });
});

describe('PackageRequirementChecker', function () {
    it('passes when stack has no package requirement', function () {
        $checker = new PackageRequirementChecker;

        expect(fn () => $checker->assertInstalled(new ApiStack))->not->toThrow(Exception::class);
    });

    it('fails when required package is missing', function () {
        $stack = new class implements PresentationStackContract
        {
            public function controllerReturn(string $action, PresentationGenerationContext $context): string
            {
                return '';
            }

            public function outputFolder(): ?string
            {
                return null;
            }

            public function requiresPackage(): ?string
            {
                return 'arc/fake-nonexistent-package';
            }

            public static function key(): string
            {
                return 'fake';
            }
        };

        $checker = new PackageRequirementChecker;

        expect(fn () => $checker->assertInstalled($stack))
            ->toThrow(MissingPackageRequirementException::class, 'composer require arc/fake-nonexistent-package');
    });
});

describe('CoreServiceProvider presentation boot validation', function () {
    it('registers presentation stack registry from config', function () {
        $registry = $this->app->make(PresentationStackRegistry::class);

        expect($registry)->toBeInstanceOf(PresentationStackRegistry::class)
            ->and($registry->resolve())->toBeInstanceOf(ApiStack::class);
    });

    it('fails boot when default_stack is invalid', function () {
        $this->app->forgetInstance(PresentationStackRegistry::class);
        $this->app['config']->set('laravarc.default_stack', 'unknown');

        $this->app->singleton(PresentationStackRegistry::class, function ($app) {
            return PresentationStackRegistry::fromConfig(
                config('laravarc.stacks', []),
                (string) config('laravarc.default_stack', 'api'),
                $app,
            );
        });

        expect(fn () => $this->app->make(PresentationStackRegistry::class))
            ->toThrow(\InvalidArgumentException::class, 'Default presentation stack [unknown]');
    });
});