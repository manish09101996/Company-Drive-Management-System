<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileUserPermission;
use App\Models\Folder;
use App\Models\FolderUserPermission;
use App\Models\User;

class PermissionService
{
    /**
     * Check if a user can perform an action on a folder.
     * Actions: 'view', 'upload', 'download', 'edit', 'delete', 'share'
     */
    public function canAccessFolder(User $user, ?Folder $folder, string $action = 'view'): bool
    {
        if (!$user->isActive()) {
            return false;
        }

        // Super Admin has full unrestricted access across all folders
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Root level checks (the top-level Company Drive view)
        if ($folder === null) {
            return match ($action) {
                // All active employees can view the drive root to see their own assigned folder
                'view' => $user->hasPermission('files.view'),
                // Only Super Admin can create folders or upload files at the root level
                'upload', 'create' => false,
                'edit', 'rename', 'move', 'delete' => false,
                default => false,
            };
        }

        // Direct explicit folder permission for this user
        $directPerm = FolderUserPermission::where('folder_id', $folder->id)
            ->where('user_id', $user->id)
            ->first();

        if ($directPerm) {
            $column = 'can_' . $action;
            if (isset($directPerm->{$column})) {
                return (bool) $directPerm->{$column};
            }
        }

        // Inherited folder permission from parent hierarchy
        $parent = $folder->parent;
        while ($parent) {
            $parentPerm = FolderUserPermission::where('folder_id', $parent->id)
                ->where('user_id', $user->id)
                ->first();

            if ($parentPerm) {
                $column = 'can_' . $action;
                if (isset($parentPerm->{$column})) {
                    return (bool) $parentPerm->{$column};
                }
            }
            $parent = $parent->parent;
        }

        // Check assigned-user relationship (direct or inherited from parent)
        $effectiveAssignedUserId = $folder->getEffectiveAssignedUserId();

        if ($effectiveAssignedUserId !== null) {
            if ($effectiveAssignedUserId === $user->id) {
                // If this is the main root folder itself (parent_id is null)
                if ($folder->parent_id === null) {
                    return match ($action) {
                        'view', 'download', 'upload' => true,
                        // Protect main root folders from accidental deletion or renaming by employees
                        'delete', 'rename', 'edit', 'move' => false,
                        'share' => $user->hasPermission('files.share'),
                        default => true,
                    };
                }

                // Inside the user's assigned folder (subfolders): full operational management
                return match ($action) {
                    'view', 'upload', 'download' => true,
                    'edit', 'rename', 'move', 'delete' => true,
                    'share' => $user->hasPermission('files.share'),
                    default => true,
                };
            }

            // Assigned to another user: strict default-deny
            return false;
        }

        // Creator fallback only if no assigned user and no parent
        if ($folder->created_by === $user->id && $folder->parent_id !== null) {
            return true;
        }

        // Unassigned folder: default-deny (accessible only to Super Admin)
        return false;
    }

    /**
     * Check if a user can perform an action on a file.
     * Actions: 'view', 'download', 'edit', 'delete', 'share'
     */
    public function canAccessFile(User $user, File $file, string $action = 'view'): bool
    {
        if (!$user->isActive()) {
            return false;
        }

        // Super Admin has full unrestricted access
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Direct explicit file permission
        $directPerm = FileUserPermission::where('file_id', $file->id)
            ->where('user_id', $user->id)
            ->first();

        if ($directPerm) {
            $column = 'can_' . $action;
            if (isset($directPerm->{$column})) {
                return (bool) $directPerm->{$column};
            }
        }

        // Inherited permission from containing folder
        if ($file->folder_id && $file->folder) {
            $folderAction = match ($action) {
                'view' => 'view',
                'download' => 'download',
                'edit', 'rename', 'move' => 'edit',
                'delete' => 'delete',
                'share' => 'share',
                default => 'view',
            };
            return $this->canAccessFolder($user, $file->folder, $folderAction);
        }

        // Root-level file without a folder: only uploader or Super Admin can access
        if ($file->uploaded_by === $user->id) {
            return true;
        }

        // Default deny for unassigned / unmatched files
        return false;
    }
}
