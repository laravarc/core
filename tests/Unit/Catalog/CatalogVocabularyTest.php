<?php

declare(strict_types=1);

describe('catalog public vocabulary', function () {
    it('does not use retired jargon in src or README', function () {
        $root = dirname(__DIR__, 3);
        $forbidden = [
            'compound',
            'ceiling',
            'identifiers',
            'grantedAbilities',
            'fromMenuAndFeatureKeys',
        ];

        $paths = [
            ...glob($root.'/src/Catalog/*.php') ?: [],
            ...glob($root.'/src/Facades/Catalog.php') ?: [],
            $root.'/README.md',
        ];

        $violations = [];

        foreach ($paths as $path) {
            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            foreach ($forbidden as $word) {
                if (preg_match('/\b'.preg_quote($word, '/').'\b/', $contents) === 1) {
                    $violations[] = $path.' contains '.$word;
                }
            }
        }

        expect($violations)->toBe([]);
    });
});
