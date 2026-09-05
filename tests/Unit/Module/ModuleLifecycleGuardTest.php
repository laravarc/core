<?php

declare(strict_types=1);

use Laravarc\Core\Module\Exceptions\ModuleAlreadyExistsException;
use Laravarc\Core\Module\Exceptions\ModuleNotFoundException;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLifecycleGuard;

describe('ModuleLifecycleGuard', function () {
    beforeEach(function () {
        $this->guard = new ModuleLifecycleGuard;
        $this->modulesPath = sys_get_temp_dir().'/arc-guard-'.uniqid('', true);
        mkdir($this->modulesPath, 0777, true);
    });

    afterEach(function () {
        if (is_dir($this->modulesPath.'/Product')) {
            rmdir($this->modulesPath.'/Product');
        }

        if (is_dir($this->modulesPath)) {
            rmdir($this->modulesPath);
        }
    });

    it('allows make when module path does not exist', function () {
        $identity = ModuleIdentity::fromPath('product', $this->modulesPath, 'App\\Modules');

        $this->guard->assertCanMake($identity, refresh: false);

        expect(true)->toBeTrue();
    });

    it('fails make when module path already exists without refresh', function () {
        $identity = ModuleIdentity::fromPath('product', $this->modulesPath, 'App\\Modules');
        mkdir($identity->rootPath, 0777, true);

        expect(fn () => $this->guard->assertCanMake($identity, refresh: false))
            ->toThrow(ModuleAlreadyExistsException::class, '--refresh');
    });

    it('requires existing module for refresh', function () {
        $identity = ModuleIdentity::fromPath('product', $this->modulesPath, 'App\\Modules');

        expect(fn () => $this->guard->assertCanMake($identity, refresh: true))
            ->toThrow(ModuleNotFoundException::class, 'not found');
    });

    it('requires existing module for remove', function () {
        $identity = ModuleIdentity::fromPath('product', $this->modulesPath, 'App\\Modules');

        expect(fn () => $this->guard->assertCanRemove($identity))
            ->toThrow(ModuleNotFoundException::class, 'not found');
    });
});
