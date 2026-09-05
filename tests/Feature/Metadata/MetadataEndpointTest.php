<?php

declare(strict_types=1);

describe('metadata endpoint', function () {
    it('serves compiled metadata when endpoint is enabled', function () {
        \Illuminate\Support\Facades\Artisan::call('laravarc:cache', ['action' => 'refresh']);
        \Illuminate\Support\Facades\Artisan::call('laravarc:metadata', ['action' => 'compile']);

        $response = $this->get('/laravarc/metadata');

        expect($response->status())->toBe(200)
            ->and($response->json('modules'))->toBeArray();
    });

    it('returns service unavailable when artifact is missing', function () {
        \Illuminate\Support\Facades\Artisan::call('laravarc:cache', ['action' => 'clear']);

        $response = $this->get('/laravarc/metadata');

        expect($response->status())->toBe(503)
            ->and($response->json('message'))->toContain('laravarc:metadata compile');
    });
});
