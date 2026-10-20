<?php

declare(strict_types=1);

use Laravarc\Core\Metadata\Support\PathValidator;

describe('PathValidator menu paths', function () {
    it('accepts a valid absolute menu path', function () {
        $validator = new PathValidator;

        expect($validator->validateMenuPath('/menus'))->toBe('/menus');
    });

    it('normalises duplicate slashes', function () {
        $validator = new PathValidator;

        expect($validator->validateMenuPath('//menus///roles'))->toBe('/menus/roles');
    });

    it('normalises a missing leading slash', function () {
        $validator = new PathValidator;

        expect($validator->validateMenuPath('menus'))->toBe('/menus');
    });

    it('normalises a trailing slash', function () {
        $validator = new PathValidator;

        expect($validator->validateMenuPath('/menus/'))->toBe('/menus');
    });

    it('normalises whitespace to hyphens', function () {
        $validator = new PathValidator;

        expect($validator->validateMenuPath('/menu s'))->toBe('/menu-s');
    });

    it('rejects dynamic segments', function (string $path) {
        $validator = new PathValidator;

        expect(fn () => $validator->validateMenuPath($path))
            ->toThrow(InvalidArgumentException::class, 'dynamic segments');
    })->with(['/menus/{id}', '/menus/:id']);

    it('normalises uppercase and camelCase to kebab-case', function () {
        $validator = new PathValidator;

        expect($validator->validateMenuPath('/Menus'))->toBe('/menus')
            ->and($validator->validateMenuPath('booksCompany'))->toBe('/books-company');
    });

    it('normalises dots, underscores, and duplicate slashes', function () {
        $validator = new PathValidator;

        expect($validator->validateMenuPath('books.company'))->toBe('/books/company')
            ->and($validator->validateMenuPath('books/company'))->toBe('/books/company')
            ->and($validator->validateMenuPath('/books.company/'))->toBe('/books/company')
            ->and($validator->validateMenuPath('books_company'))->toBe('/books-company')
            ->and($validator->validateMenuPath('books//company'))->toBe('/books/company');
    });

    it('rejects a reserved path on a surface', function () {
        $validator = new PathValidator;

        expect(fn () => $validator->validateMenuPath('/login', 'admin', ['/login']))
            ->toThrow(InvalidArgumentException::class, 'reserved');
    });

    it('rejects a duplicate case-insensitive path on the same surface', function () {
        $validator = new PathValidator;

        expect(fn () => $validator->assertUniquePath('/Menus', ['/menus'], 'admin'))
            ->toThrow(InvalidArgumentException::class, 'already used');
    });

    it('allows the primary tab to share the menu path once', function () {
        $validator = new PathValidator;

        $validator->assertUniquePath('/menus', ['/menus'], 'admin', allowPrimaryTab: true);
        $validator->assertSinglePrimaryTab('/menus', ['/menus/features'], 'admin');

        expect(true)->toBeTrue();
    });

    it('rejects a second primary tab for the same menu', function () {
        $validator = new PathValidator;

        expect(fn () => $validator->assertSinglePrimaryTab('/menus', ['/menus'], 'admin'))
            ->toThrow(InvalidArgumentException::class, 'already has a primary tab');
    });

    it('rejects an overlong path', function () {
        $validator = new PathValidator;

        expect(fn () => $validator->validateMenuPath('/'.str_repeat('a', PathValidator::MAX_LENGTH)))
            ->toThrow(InvalidArgumentException::class, 'must not exceed');
    });
});

describe('PathValidator feature suffixes', function () {
    it('combines a relative suffix with its menu path', function () {
        $validator = new PathValidator;

        expect($validator->combine('/menus', '/features'))->toBe('/menus/features')
            ->and($validator->combine('/menus', '/'))->toBe('/menus')
            ->and($validator->combine('/menus', ''))->toBe('/menus')
            ->and($validator->combine(null, ''))->toBeNull();
    });

    it('returns a normalised relative suffix', function () {
        $validator = new PathValidator;

        expect($validator->validateFeatureSuffix('//features', '/menus'))->toBe('/features')
            ->and($validator->validateFeatureSuffix('features', '/menus'))->toBe('/features')
            ->and($validator->validateFeatureSuffix('/features/', '/menus'))->toBe('/features')
            ->and($validator->validateFeatureSuffix('roleAbilities', '/menus'))->toBe('/role-abilities');
    });

    it('rejects a feature path that repeats the menu path and suggests the relative suffix', function () {
        $validator = new PathValidator;

        expect(fn () => $validator->validateFeatureSuffix('/menus/features', '/menus'))
            ->toThrow(InvalidArgumentException::class, 'did you mean "/features"');
    });

    it('rejects an empty suffix when the feature has a menu', function () {
        $validator = new PathValidator;

        expect(fn () => $validator->validateFeatureSuffix('', '/menus'))
            ->toThrow(InvalidArgumentException::class, 'use "/" for the primary tab');
    });

    it('rejects a non-empty suffix when the feature has no menu', function () {
        $validator = new PathValidator;

        expect(fn () => $validator->validateFeatureSuffix('/features', null))
            ->toThrow(InvalidArgumentException::class, 'requires a parent menu path');
    });

    it('normalises a suffix with a trailing slash, except the primary tab', function () {
        $validator = new PathValidator;

        expect($validator->validateFeatureSuffix('/features/', '/menus'))->toBe('/features')
            ->and($validator->validateFeatureSuffix('/', '/menus'))->toBe('/');
    });

    it('recognises the primary tab suffix', function () {
        $validator = new PathValidator;

        expect($validator->isPrimaryTabSuffix('/'))->toBeTrue()
            ->and($validator->isPrimaryTabSuffix(''))->toBeFalse()
            ->and($validator->isPrimaryTabSuffix('/features'))->toBeFalse();
    });

    it('rejects a reserved combined feature path', function () {
        $validator = new PathValidator;

        expect(fn () => $validator->validateFeatureSuffix('/dashboard', '/admin', 'admin', ['/admin/dashboard']))
            ->toThrow(InvalidArgumentException::class, 'reserved');
    });
});
