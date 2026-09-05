<?php

declare(strict_types=1);

namespace Laravarc\Core\I18n;

use Illuminate\Http\Request;

/**
 * Runtime locale helpers for multilingual apps.
 *
 * - {@see default()} / {@see fallback()} read Laravel {@code app.locale} / {@code app.fallback_locale}.
 * - {@see supported()} reads {@code laravarc.i18n.supported_locales}, then falls back to
 *   {@code app.supported_locales} for backward compatibility.
 * - Generator default locale remains {@code laravarc.locale} (separate concern).
 */
final class Locales
{
    /** @return list<string> */
    public static function supported(): array
    {
        $locales = self::normalizeList(config('laravarc.i18n.supported_locales', []));

        if ($locales === []) {
            $locales = self::normalizeList(config('app.supported_locales', []));
        }

        return $locales !== [] ? $locales : [self::getDefaultLocale()];
    }

    public static function default(): string
    {
        $default = self::getDefaultLocale();
        $supported = self::supported();

        return in_array($default, $supported, true) ? $default : $supported[0];
    }

    public static function fallback(): string
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $supported = self::supported();

        return in_array($fallback, $supported, true) ? $fallback : $supported[0];
    }

    /**
     * Resolve locale for a request: query param → authenticated user locale → app default.
     *
     * Does not clamp to {@see supported()}; callers that need validation should do so explicitly.
     */
    public static function fromRequest(Request $request, string $queryKey = 'locale'): string
    {
        $fromQuery = $request->query($queryKey);
        if (is_string($fromQuery) && $fromQuery !== '') {
            return $fromQuery;
        }

        $user = $request->user();
        if ($user !== null) {
            $userLocale = data_get($user, 'locale');
            if (is_string($userLocale) && $userLocale !== '') {
                return $userLocale;
            }
        }

        return self::default();
    }

    /**
     * @param  list<string>  $baseRules
     * @return array<string, list<string>>
     */
    public static function translationFieldRules(
        string $field,
        array $baseRules,
        bool $required = true,
    ): array {
        $rules = [
            $field => [$required ? 'required' : 'sometimes', 'array'],
        ];

        foreach (self::supported() as $locale) {
            $rules["{$field}.{$locale}"] = $baseRules;
        }

        return $rules;
    }

    private static function getDefaultLocale(): string
    {
        return (string) config('app.locale', 'id');
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private static function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $value),
            static fn (string $locale): bool => $locale !== '',
        ));
    }
}
