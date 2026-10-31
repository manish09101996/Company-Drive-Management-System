<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use App\Services\PermissionService;

class FilePolicy
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function view(User $user, File $file): bool
    {
        return $this->permissionService->canAccessFile($user, $file, 'view');
    }

    public function download(User $user, File $file): bool
    {
        return $this->permissionService->canAccessFile($user, $file, 'download');
    }

    public function update(User $user, File $file): bool
    {
        return $this->permissionService->canAccessFile($user, $file, 'edit');
    }

    public function delete(User $user, File $file): bool
    {
        return $this->permissionService->canAccessFile($user, $file, 'delete');
    }

    public function restore(User $user, File $file): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return $this->permissionService->canAccessFile($user, $file, 'delete');
    }

    public function share(User $user, File $file): bool
    {
        return $this->permissionService->canAccessFile($user, $file, 'share');
    }
}
