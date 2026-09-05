<?php

declare(strict_types=1);

namespace App\Modules\Admin\User\Policies;

use App\Modules\Admin\User\Models\User;

final class UserPolicy
{
    public function viewAny(?object $user): bool
    {
        return true;
    }

    public function view(?object $user, User $model): bool
    {
        return true;
    }
}
