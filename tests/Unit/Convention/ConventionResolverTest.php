<?php

declare(strict_types=1);

use Laravarc\Core\Convention\DefaultLayerResolver;
use Laravarc\Core\Convention\DefaultModuleKeyResolver;
use Laravarc\Core\Convention\DefaultRequestResolver;
use Laravarc\Core\Convention\Exceptions\InvalidLayerException;
use Laravarc\Core\Convention\Layer;
use Laravarc\Core\Module\ModuleIdentity;

describe('Layer', function () {
    it('maps each layer to a flat module folder', function () {
        expect(Layer::Controller->folder())->toBe('Controllers')
            ->and(Layer::FormRequest->folder())->toBe('FormRequests')
            ->and(Layer::Service->folder())->toBe('Services')
            ->and(Layer::Repository->folder())->toBe('Repositories')
            ->and(Layer::Model->folder())->toBe('Models')
            ->and(Layer::Policy->folder())->toBe('Policies')
            ->and(Layer::Resource->folder())->toBe('Resources')
            ->and(Layer::Event->folder())->toBe('Events')
            ->and(Layer::Listener->folder())->toBe('Listeners');
    });

    it('rejects unknown layer roles', function () {
        expect(fn () => Layer::fromString('unknown'))
            ->toThrow(InvalidLayerException::class, 'Unknown layer role');
    });
});

describe('DefaultLayerResolver', function () {
    beforeEach(function () {
        $this->resolver = new DefaultLayerResolver;
        $this->modulesPath = sys_get_temp_dir().'/arc-convention-'.uniqid('', true);
        mkdir($this->modulesPath, 0777, true);
        $this->identity = ModuleIdentity::fromPath('admin/user', $this->modulesPath, 'App\\Modules');
    });

    afterEach(function () {
        if (is_dir($this->modulesPath)) {
            rmdir($this->modulesPath);
        }
    });

    it('resolves controller class and relative path', function () {
        $resolved = $this->resolver->resolve($this->identity, Layer::Controller);

        expect($resolved->className)->toBe('App\\Modules\\Admin\\User\\Controllers\\UserController')
            ->and($resolved->relativePath)->toBe('Controllers/UserController.php');
    });

    it('resolves model service repository policy and resource classes', function () {
        expect($this->resolver->resolve($this->identity, Layer::Model)->className)
            ->toBe('App\\Modules\\Admin\\User\\Models\\User')
            ->and($this->resolver->resolve($this->identity, Layer::Service)->className)
            ->toBe('App\\Modules\\Admin\\User\\Services\\UserService')
            ->and($this->resolver->resolve($this->identity, Layer::Repository)->className)
            ->toBe('App\\Modules\\Admin\\User\\Repositories\\UserRepository')
            ->and($this->resolver->resolve($this->identity, Layer::Policy)->className)
            ->toBe('App\\Modules\\Admin\\User\\Policies\\UserPolicy')
            ->and($this->resolver->resolve($this->identity, Layer::Resource)->className)
            ->toBe('App\\Modules\\Admin\\User\\Resources\\UserResource');
    });

    it('requires explicit names for event and listener layers', function () {
        $event = $this->resolver->resolve($this->identity, Layer::Event, 'UserCreated');
        $listener = $this->resolver->resolve($this->identity, Layer::Listener, 'SendWelcomeEmail');

        expect($event->className)->toBe('App\\Modules\\Admin\\User\\Events\\UserCreated')
            ->and($listener->className)->toBe('App\\Modules\\Admin\\User\\Listeners\\SendWelcomeEmail');
    });

    it('rejects form request resolution through layer resolver', function () {
        expect(fn () => $this->resolver->resolve($this->identity, Layer::FormRequest))
            ->toThrow(InvalidLayerException::class, 'RequestResolver');
    });
});

describe('DefaultModuleKeyResolver', function () {
    it('resolves dot notation module keys', function () {
        $modulesPath = sys_get_temp_dir().'/arc-key-'.uniqid('', true);
        mkdir($modulesPath, 0777, true);

        $identity = ModuleIdentity::fromPath('admin/user', $modulesPath, 'App\\Modules');
        $key = (new DefaultModuleKeyResolver)->resolve($identity);

        expect($key)->toBe('admin.user');

        rmdir($modulesPath);
    });
});

describe('DefaultRequestResolver', function () {
    beforeEach(function () {
        $this->resolver = new DefaultRequestResolver;
        $this->modulesPath = sys_get_temp_dir().'/arc-request-'.uniqid('', true);
        mkdir($this->modulesPath, 0777, true);
        $this->identity = ModuleIdentity::fromPath('admin/user', $this->modulesPath, 'App\\Modules');
    });

    afterEach(function () {
        if (is_dir($this->modulesPath)) {
            rmdir($this->modulesPath);
        }
    });

    it('resolves CRUD form request classes', function () {
        expect($this->resolver->resolve($this->identity, 'store')->className)
            ->toBe('App\\Modules\\Admin\\User\\FormRequests\\StoreUserRequest')
            ->and($this->resolver->resolve($this->identity, 'update')->className)
            ->toBe('App\\Modules\\Admin\\User\\FormRequests\\UpdateUserRequest')
            ->and($this->resolver->resolve($this->identity, 'destroy')->className)
            ->toBe('App\\Modules\\Admin\\User\\FormRequests\\DestroyUserRequest');
    });

    it('supports custom action names', function () {
        $resolved = $this->resolver->resolve($this->identity, 'archive');

        expect($resolved->className)->toBe('App\\Modules\\Admin\\User\\FormRequests\\ArchiveUserRequest')
            ->and($resolved->relativePath)->toBe('FormRequests/ArchiveUserRequest.php');
    });
});

describe('Convention container bindings', function () {
    it('registers default resolvers from config', function () {
        expect(app(\Laravarc\Core\Contracts\LayerResolver::class))
            ->toBeInstanceOf(DefaultLayerResolver::class)
            ->and(app(\Laravarc\Core\Contracts\ModuleKeyResolver::class))
            ->toBeInstanceOf(DefaultModuleKeyResolver::class)
            ->and(app(\Laravarc\Core\Contracts\RequestResolver::class))
            ->toBeInstanceOf(DefaultRequestResolver::class);
    });
});
