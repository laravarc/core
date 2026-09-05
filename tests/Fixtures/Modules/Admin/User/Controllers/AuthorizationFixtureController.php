<?php

declare(strict_types=1);

namespace App\Modules\Admin\User\Controllers;

final class AuthorizationFixtureController
{
    public function index(): string
    {
        return 'ok';
    }

    public function show(mixed $id): string
    {
        return 'show';
    }
}
