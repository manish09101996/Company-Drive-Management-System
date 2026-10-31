<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('users.manage');
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('users.manage') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('users.manage');
    }

    public function update(User $user, User $model): bool
    {
        // Protect Super Admin from being edited by non-super-admins
        if ($model->isSuperAdmin() && !$user->isSuperAdmin()) {
            return false;
        }

        return $user->isSuperAdmin() || $user->hasPermission('users.manage') || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        // Cannot delete oneself
        if ($user->id === $model->id) {
            return false;
        }

        // Super Admin account cannot be deleted
        if ($model->isSuperAdmin()) {
            return false;
        }

        return $user->isSuperAdmin() || $user->hasPermission('users.manage');
    }
}
