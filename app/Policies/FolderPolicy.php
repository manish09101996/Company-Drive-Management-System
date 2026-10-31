<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use App\Services\PermissionService;

class FolderPolicy
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function view(User $user, ?Folder $folder = null): bool
    {
        return $this->permissionService->canAccessFolder($user, $folder, 'view');
    }

    public function create(User $user, ?Folder $parent = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Subfolders inside an assigned folder
        if ($parent) {
            return $this->permissionService->canAccessFolder($user, $parent, 'upload');
        }

        // At root level, only Super Admin may create main folders
        return false;
    }

    public function upload(User $user, ?Folder $folder = null): bool
    {
        return $this->permissionService->canAccessFolder($user, $folder, 'upload');
    }

    public function update(User $user, Folder $folder): bool
    {
        return $this->permissionService->canAccessFolder($user, $folder, 'edit');
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $this->permissionService->canAccessFolder($user, $folder, 'delete');
    }

    public function share(User $user, Folder $folder): bool
    {
        return $this->permissionService->canAccessFolder($user, $folder, 'share');
    }
}
