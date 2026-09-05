<?php

declare(strict_types=1);

use Laravarc\Core\Module\Exceptions\InvalidModulePathException;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Module\ModulePathValidator;

describe('ModuleLayout', function () {
    it('defines mandatory CRUD folders', function () {
        expect(ModuleLayout::mandatoryFolders())->toBe([
            'Controllers',
            'FormRequests',
            'Services',
            'Repositories',
            'Policies',
            'Models',
            'Database/Migrations',
            'Routes',
        ]);
    });

    it('defines preset optional folders', function () {
        expect(ModuleLayout::presetOptionalFolders())->toBe([
            'Events',
            'Listeners',
            'Database/Seeders',
        ]);
    });

    it('defines stack optional folders', function () {
        expect(ModuleLayout::stackOptionalFolders())->toBe([
            'Resources',
            'Views',
        ]);
    });
});

describe('ModulePathValidator', function () {
    beforeEach(function () {
        $this->validator = new ModulePathValidator;
        $this->modulesPath = sys_get_temp_dir().'/arc-validator-'.uniqid('', true);
        mkdir($this->modulesPath, 0777, true);
    });

    afterEach(function () {
        if (is_dir($this->modulesPath)) {
            rmdir($this->modulesPath);
        }
    });

    it('normalizes nested module paths to StudlyCase segments', function () {
        expect($this->validator->normalize('/admin/user/'))
            ->toBe(['Admin', 'User']);
    });

    it('rejects empty paths', function () {
        expect(fn () => $this->validator->normalize(''))
            ->toThrow(InvalidModulePathException::class, 'must not be empty');
    });

    it('rejects path traversal', function () {
        expect(fn () => $this->validator->normalize('admin/../secret'))
            ->toThrow(InvalidModulePathException::class, 'path traversal');
    });

    it('rejects reserved layer segments case-insensitively', function () {
        expect(fn () => $this->validator->normalize('admin/controllers'))
            ->toThrow(InvalidModulePathException::class, 'reserved');

        expect(fn () => $this->validator->normalize('Models/user'))
            ->toThrow(InvalidModulePathException::class, 'reserved');
    });

    it('resolves module root under modules path using StudlyCase segments', function () {
        $root = $this->validator->resolveRootPath($this->modulesPath, 'admin/user');

        expect($root)->toBe($this->modulesPath.DIRECTORY_SEPARATOR.'Admin'.DIRECTORY_SEPARATOR.'User');
    });
});

describe('ModuleIdentity', function () {
    beforeEach(function () {
        $this->modulesPath = sys_get_temp_dir().'/arc-identity-'.uniqid('', true);
        mkdir($this->modulesPath, 0777, true);
    });

    afterEach(function () {
        if (is_dir($this->modulesPath)) {
            rmdir($this->modulesPath);
        }
    });

    it('derives namespace and default table from module path', function () {
        $identity = ModuleIdentity::fromPath(
            'admin/user',
            $this->modulesPath,
            'App\\Modules',
        );

        expect($identity->path)->toBe('Admin/User')
            ->and($identity->namespace)->toBe('App\\Modules\\Admin\\User')
            ->and($identity->entityName)->toBe('User')
            ->and($identity->defaultTableName)->toBe('users')
            ->and($identity->rootPath)->toBe($this->modulesPath.DIRECTORY_SEPARATOR.'Admin'.DIRECTORY_SEPARATOR.'User');
    });

    it('pluralizes irregular nouns using Laravel Str', function () {
        $identity = ModuleIdentity::fromPath(
            'admin/person',
            $this->modulesPath,
            'App\\Modules',
        );

        expect($identity->defaultTableName)->toBe('people')
            ->and($identity->entityName)->toBe('Person');
    });

    it('reports filesystem existence', function () {
        $identity = ModuleIdentity::fromPath('product', $this->modulesPath, 'App\\Modules');

        expect($identity->existsOnFilesystem())->toBeFalse();

        mkdir($identity->rootPath, 0777, true);

        expect($identity->existsOnFilesystem())->toBeTrue();

        rmdir($identity->rootPath);
    });
});
