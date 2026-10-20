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
        $normalized = $this->normalizeSlashes($path);

        if ($normalized === '' || $normalized === '/') {
            throw new InvalidArgumentException('Menu path must not be empty or root.');
        }

        $this->assertNoWhitespace($normalized, 'Menu path');
        $this->assertNoDynamicSegment($normalized, 'Menu path');

        if (! str_starts_with($normalized, '/')) {
            throw new InvalidArgumentException('Menu path must start with "/".');
        }

        if (str_ends_with($normalized, '/')) {
            throw new InvalidArgumentException('Menu path must not end with "/".');
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
        $suffix = $suffix ?? '';

        if ($menuPath === null || $menuPath === '') {
            if ($suffix !== '') {
                throw new InvalidArgumentException('Feature path requires a parent menu path.');
            }

            return '';
        }

        $normalizedMenu = $this->normalizeSlashes($menuPath);
        $normalized = $this->normalizeSlashes($suffix);

        if ($normalized === '') {
            throw new InvalidArgumentException('Feature path is required when the feature has a menu; use "/" for the primary tab.');
        }

        $this->assertNoWhitespace($normalized, 'Feature path');
        $this->assertNoDynamicSegment($normalized, 'Feature path');

        if (! str_starts_with($normalized, '/')) {
            throw new InvalidArgumentException('Feature path must be relative to its menu and start with "/".');
        }

        if ($normalized !== self::PRIMARY_TAB_SUFFIX && str_ends_with($normalized, '/')) {
            throw new InvalidArgumentException('Feature path must not end with "/" unless it is the primary tab "/".');
        }

        $this->assertSegmentGrammar($normalized, 'Feature path');

        if ($normalized !== self::PRIMARY_TAB_SUFFIX) {
            $combined = $this->combine($normalizedMenu, $normalized) ?? '';

            if ($combined !== '' && (
                $normalized === $normalizedMenu
                || str_starts_with($normalized, $normalizedMenu.'/')
            )) {
                $suggestion = substr($normalized, strlen($normalizedMenu)) ?: self::PRIMARY_TAB_SUFFIX;

                throw new InvalidArgumentException(sprintf(
                    'Feature path is relative to its menu; did you mean "%s"?',
                    $suggestion,
                ));
            }

            $this->assertLength($combined, 'Combined feature path');
            $this->assertNotReserved($combined, $surface, $reservedPaths);
        }

        return $normalized;
    }

    public function combine(?string $menuPath, ?string $suffix): ?string
    {
        $menuPath = $menuPath === null ? null : $this->normalizeSlashes($menuPath);
        $suffix = $suffix === null ? null : $this->normalizeSlashes($suffix);

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
        return $this->normalizeSlashes($suffix ?? '') === self::PRIMARY_TAB_SUFFIX;
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

    private function assertNoWhitespace(string $path, string $label): void
    {
        if (preg_match('/\s/', $path)) {
            throw new InvalidArgumentException(sprintf('%s must not contain whitespace.', $label));
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

    private function normalizeSlashes(string $path): string
    {
        return preg_replace('#/+#', '/', $path) ?? $path;
    }

    private function normalizeForComparison(string $path): string
    {
        $normalized = strtolower($this->normalizeSlashes($path));

        return $normalized === '/' ? $normalized : rtrim($normalized, '/');
    }
}
