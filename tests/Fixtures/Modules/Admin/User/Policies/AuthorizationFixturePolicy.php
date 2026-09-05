<?php

declare(strict_types=1);

namespace App\Modules\Admin\User\Policies;

use Laravarc\Core\Tests\Fixtures\Models\AuthorizationFixtureModel;

final class AuthorizationFixturePolicy
{
    public function viewAny(?object $user): bool
    {
        return true;
    }

    public function view(?object $user, AuthorizationFixtureModel $model): bool
    {
        return true;
    }
}
