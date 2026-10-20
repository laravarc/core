<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata\Support;

use InvalidArgumentException;

/**
 * Single source of truth for UI base-path rules.
 *
 * Menu paths are absolute (`/menus`). Feature paths are suffixes relative to
 * their parent menu (`/features` -> `/menus/features`); the joined value is
 * never persisted. The admin update endpoint and the metadata compile path
 * must both use this class instead of duplicating the rules.
 *
 * Input is normalised before validation: missing leading slash, dots,
 * underscores, spaces, camelCase, duplicate slashes, and trailing slashes are
 * accepted and rewritten to a lowercase kebab-case path. Dynamic segments
 * (`{id}` / `:id`) remain invalid.
 */
final class PathValidator
{
    public const MAX_LENGTH = 255;

    public const PRIMARY_TAB_SUFFIX = '/';

    /**
     * @param  list<string>  $reservedPaths
     */
    public function validateMenuPath(
        string $path,
        string $surface = 'default',
        array $reservedPaths = [],
    ): string {
        $this->assertNoDynamicSegment($path, 'Menu path');

        $normalized = $this->normalize($path);

        if ($normalized === '' || $normalized === self::PRIMARY_TAB_SUFFIX) {
            throw new InvalidArgumentException('Menu path must not be empty or root.');
        }

        $this->assertSegmentGrammar($normalized, 'Menu path');
        $this->assertLength($normalized, 'Menu path');
        $this->assertNotReserved($normalized, $surface, $reservedPaths);

        return $normalized;
    }

    /**
     * @param  list<string>  $reservedPaths
     */
    public function validateFeatureSuffix(
        ?string $suffix,
        ?string $menuPath,
        string $surface = 'default',
        array $reservedPaths = [],
    ): string {
        $rawSuffix = trim($suffix ?? '');

        $this->assertNoDynamicSegment($rawSuffix, 'Feature path');

        if ($menuPath === null || $menuPath === '') {
            if ($rawSuffix !== '') {
                throw new InvalidArgumentException('Feature path requires a parent menu path.');
            }

            return '';
        }

        $normalizedMenu = $this->normalize($menuPath);

        if ($rawSuffix === self::PRIMARY_TAB_SUFFIX) {
            return self::PRIMARY_TAB_SUFFIX;
        }

        $normalized = $this->normalize($rawSuffix);

        if ($normalized === '') {
            throw new InvalidArgumentException('Feature path is required when the feature has a menu; use "/" for the primary tab.');
        }

        $this->assertSegmentGrammar($normalized, 'Feature path');

        if ($normalized !== self::PRIMARY_TAB_SUFFIX) {
            // Forgiving double-prefix: if the admin pasted the menu path again,
            // strip it instead of rejecting. `/books/company` -> `/company`;
            // exactly `/books` -> `/` (primary tab).
            if ($normalized === $normalizedMenu || str_starts_with($normalized, $normalizedMenu.'/')) {
                $normalized = substr($normalized, strlen($normalizedMenu)) ?: self::PRIMARY_TAB_SUFFIX;

                if ($normalized !== self::PRIMARY_TAB_SUFFIX && ! str_starts_with($normalized, '/')) {
                    $normalized = '/'.$normalized;
                }
            }

            $combined = $this->combine($normalizedMenu, $normalized) ?? '';

            $this->assertLength($combined, 'Combined feature path');
            $this->assertNotReserved($combined, $surface, $reservedPaths);
        }

        return $normalized;
    }

    public function combine(?string $menuPath, ?string $suffix): ?string
    {
        $menuPath = $menuPath === null ? null : $this->normalize($menuPath);
        $suffix = $suffix === null ? null : $this->normalize($suffix);

        if ($menuPath === null || $menuPath === '') {
            return $suffix === null || $suffix === '' ? null : $suffix;
        }

        if ($suffix === null || $suffix === '' || $suffix === self::PRIMARY_TAB_SUFFIX) {
            return $menuPath;
        }

        return rtrim($menuPath, '/').'/'.ltrim($suffix, '/');
    }

    public function isPrimaryTabSuffix(?string $suffix): bool
    {
        return trim($suffix ?? '') === self::PRIMARY_TAB_SUFFIX;
    }

    /**
     * @param  list<string>  $existingPaths
     */
    public function assertUniquePath(
        string $candidate,
        array $existingPaths,
        string $surface = 'default',
        bool $allowPrimaryTab = false,
    ): void {
        $normalizedCandidate = $this->normalizeForComparison($candidate);

        foreach ($existingPaths as $existing) {
            if ($this->normalizeForComparison($existing) !== $normalizedCandidate) {
                continue;
            }

            if ($allowPrimaryTab) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Path "%s" is already used on surface "%s".',
                $candidate,
                $surface,
            ));
        }
    }

    /**
     * A menu may have at most one primary tab; its combined path equals the
     * menu path, so this is intentionally separate from normal uniqueness.
     *
     * @param  list<string>  $existingFeaturePaths
     */
    public function assertSinglePrimaryTab(
        string $menuPath,
        array $existingFeaturePaths,
        string $surface = 'default',
    ): void {
        $normalizedMenu = $this->normalizeForComparison($menuPath);

        foreach ($existingFeaturePaths as $existing) {
            if ($this->normalizeForComparison($existing) === $normalizedMenu) {
                throw new InvalidArgumentException(sprintf(
                    'Menu "%s" already has a primary tab on surface "%s".',
                    $menuPath,
                    $surface,
                ));
            }
        }
    }

    /**
     * @param  list<string>  $reservedPaths
     */
    private function assertNotReserved(string $path, string $surface, array $reservedPaths): void
    {
        $candidate = $this->normalizeForComparison($path);

        foreach ($reservedPaths as $reserved) {
            if ($this->normalizeForComparison($reserved) === $candidate) {
                throw new InvalidArgumentException(sprintf(
                    'Path "%s" is reserved on surface "%s".',
                    $path,
                    $surface,
                ));
            }
        }
    }

    private function assertSegmentGrammar(string $path, string $label): void
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));

        foreach ($segments as $segment) {
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $segment)) {
                throw new InvalidArgumentException(sprintf(
                    '%s segment "%s" must be lowercase slug/kebab-case.',
                    $label,
                    $segment,
                ));
            }
        }
    }

    private function assertNoDynamicSegment(string $path, string $label): void
    {
        if (str_contains($path, '{') || str_contains($path, '}') || str_contains($path, ':')) {
            throw new InvalidArgumentException(sprintf('%s must not contain dynamic segments.', $label));
        }
    }

    private function assertLength(string $path, string $label): void
    {
        if (strlen($path) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                '%s must not exceed %d characters.',
                $label,
                self::MAX_LENGTH,
            ));
        }
    }

    /**
     * Normalise forgiving admin input to a lowercase kebab-case absolute path.
     *
     * Examples:
     *  - `menus` -> `/menus`
     *  - `books.company` -> `/books/company`
     *  - `books/company` -> `/books/company`
     *  - `/books/company/` -> `/books/company`
     *  - `booksCompany` -> `/books-company`
     *  - `books//company` -> `/books/company`
     */
    private function normalize(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);
        $path = str_replace(['.', '_', ' '], ['/', '-', '-'], $path);

        // split camelCase / PascalCase boundaries before lowercasing
        $path = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $path) ?? $path;
        $path = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $path) ?? $path;

        $path = strtolower($path);
        $path = preg_replace('/[^a-z0-9\/-]+/', '-', $path) ?? $path;
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = preg_replace('/-+/', '-', $path) ?? $path;

        $segments = array_map(
            static fn (string $segment): string => trim($segment, '-'),
            explode('/', $path),
        );

        $path = implode('/', $segments);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = trim($path, '/');

        return $path === '' ? '' : '/'.$path;
    }

    private function normalizeForComparison(string $path): string
    {
        $normalized = $this->normalize($path);

        return $normalized === self::PRIMARY_TAB_SUFFIX ? $normalized : rtrim($normalized, '/');
    }
}
