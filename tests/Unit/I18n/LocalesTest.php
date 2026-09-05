<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Laravarc\Core\I18n\Locales;

describe('Locales runtime helpers', function () {
    beforeEach(function () {
        config([
            'app.locale' => 'id',
            'app.fallback_locale' => 'en',
            'app.supported_locales' => [],
            'laravarc.i18n.supported_locales' => ['id', 'en'],
        ]);
    });

    it('reads supported locales from laravarc.i18n', function () {
        expect(Locales::supported())->toBe(['id', 'en']);
    });

    it('falls back to app.supported_locales when laravarc list is empty', function () {
        config([
            'laravarc.i18n.supported_locales' => [],
            'app.supported_locales' => ['en', 'fr'],
        ]);

        expect(Locales::supported())->toBe(['en', 'fr']);
    });

    it('falls back to app.locale when no supported lists are set', function () {
        config([
            'laravarc.i18n.supported_locales' => [],
            'app.supported_locales' => [],
            'app.locale' => 'id',
        ]);

        expect(Locales::supported())->toBe(['id']);
    });

    it('returns default within supported list', function () {
        expect(Locales::default())->toBe('id');
    });

    it('returns first supported when app.locale is not in the list', function () {
        config([
            'app.locale' => 'ja',
            'laravarc.i18n.supported_locales' => ['en', 'id'],
        ]);

        expect(Locales::default())->toBe('en');
    });

    it('returns fallback within supported list', function () {
        expect(Locales::fallback())->toBe('en');
    });

    it('builds translation field rules per supported locale', function () {
        $rules = Locales::translationFieldRules('labels', ['required', 'string'], required: true);

        expect($rules)->toBe([
            'labels' => ['required', 'array'],
            'labels.id' => ['required', 'string'],
            'labels.en' => ['required', 'string'],
        ]);
    });

    it('resolves locale from request query first', function () {
        $request = Request::create('/x', 'GET', ['locale' => 'en']);

        expect(Locales::fromRequest($request))->toBe('en');
    });

    it('resolves locale from authenticated user when query missing', function () {
        $request = Request::create('/x', 'GET');
        $request->setUserResolver(static fn () => (object) ['locale' => 'en']);

        expect(Locales::fromRequest($request))->toBe('en');
    });

    it('resolves to app default when query and user locale missing', function () {
        $request = Request::create('/x', 'GET');
        $request->setUserResolver(static fn () => null);

        expect(Locales::fromRequest($request))->toBe('id');
    });
});
