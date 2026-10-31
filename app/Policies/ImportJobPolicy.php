<?php

namespace App\Policies;

use App\Models\ImportJob;
use App\Models\User;

class ImportJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('imports.manage');
    }

    public function view(User $user, ImportJob $job): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('imports.manage');
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('imports.manage');
    }

    public function update(User $user, ImportJob $job): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('imports.manage');
    }

    public function delete(User $user, ImportJob $job): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('imports.manage');
    }
}
