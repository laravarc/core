<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

describe('laravarc:ai-rule version', function () {
    it('prints human-readable version output', function () {
        $exitCode = Artisan::call('laravarc:ai-rule', ['action' => 'version']);
        $output = Artisan::output();

        expect($exitCode)->toBe(0, $output)
            ->and($output)->toContain('Laravarc AI Rules')
            ->and($output)->toContain('Package: laravarc/core')
            ->and($output)->toContain('Rules version: 1.2.0')
            ->and($output)->toContain('arc.bootstrap (1.1.0) rules/00-bootstrap.md')
            ->and($output)->toContain('arc.core.principles (1.1.0) rules/10-core-principles.md')
            ->and($output)->toContain('arc.scope.boundaries (1.2.0) rules/15-scope-and-boundaries.md')
            ->and($output)->toContain('arc.module.layout (1.2.0) rules/20-module-layout.md')
            ->and($output)->toContain('arc.separation.of.concern (1.2.0) rules/25-separation-of-concern.md')
            ->and($output)->toContain('arc.generation.workflow (1.2.0) rules/30-generation-workflow.md')
            ->and($output)->toContain('arc.cli.commands (1.1.3) rules/40-cli-commands.md')
            ->and($output)->toContain('arc.metadata.authorization (1.2.0) rules/50-metadata-authorization.md')
            ->and($output)->toContain('arc.routing.presentation (1.1.0) rules/60-routing-presentation.md')
            ->and($output)->toContain('arc.anti.patterns (1.2.0) rules/70-anti-patterns.md')
            ->and($output)->toContain('arc.checklist (1.1.0) rules/99-checklist.md');
    });

    it('prints machine-readable json output', function () {
        $exitCode = Artisan::call('laravarc:ai-rule', [
            'action' => 'version',
            '--json' => true,
        ]);
        $output = Artisan::output();

        expect($exitCode)->toBe(0, $output);

        $decoded = json_decode($output, true);

        expect($decoded)->toBeArray()
            ->and($decoded['package'])->toBe('laravarc/core')
            ->and($decoded['rules_version'])->toBe('1.2.0')
            ->and($decoded['bootstrap_readme'])->toBe('README.md')
            ->and($decoded['package_path'])->toEndWith('/ai-rules')
            ->and($decoded['rules'])->toHaveCount(11)
            ->and($decoded['rules'][0]['id'])->toBe('arc.bootstrap')
            ->and($decoded['rules'][0]['checksum'])->toStartWith('sha256:');
    });

    it('rejects unsupported actions', function () {
        $exitCode = Artisan::call('laravarc:ai-rule', ['action' => 'install']);
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('Supported actions: version.');
    });
});
