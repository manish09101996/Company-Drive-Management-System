<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileUserPermission;
use App\Models\Folder;
use App\Models\FolderUserPermission;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShareController extends Controller
{
    /**
     * Get permission assignments for a file or folder.
     */
    public function getPermissions(Request $request): JsonResponse
    {
        $type = $request->query('type'); // 'file' or 'folder'
        $id = (int) $request->query('id');

        if ($type === 'folder') {
            $folder = Folder::findOrFail($id);
            $this->authorize('share', $folder);

            $permissions = FolderUserPermission::with('user:id,name,email')
                ->where('folder_id', $id)
                ->get();
        } else {
            $file = File::findOrFail($id);
            $this->authorize('share', $file);

            $permissions = FileUserPermission::with('user:id,name,email')
                ->where('file_id', $id)
                ->get();
        }

        return response()->json([
            'success' => true,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Grant or update user permissions.
     */
    public function updatePermission(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:file,folder',
            'id' => 'required|integer',
            'user_id' => 'required|exists:users,id',
            'can_view' => 'boolean',
            'can_upload' => 'boolean',
            'can_download' => 'boolean',
            'can_edit' => 'boolean',
            'can_delete' => 'boolean',
            'can_share' => 'boolean',
        ]);

        $type = $request->type;
        $id = (int) $request->id;
        $targetUserId = (int) $request->user_id;

        if ($type === 'folder') {
            $folder = Folder::findOrFail($id);
            $this->authorize('share', $folder);

            $perm = FolderUserPermission::updateOrCreate(
                ['folder_id' => $id, 'user_id' => $targetUserId],
                [
                    'can_view' => $request->boolean('can_view', true),
                    'can_upload' => $request->boolean('can_upload', false),
                    'can_download' => $request->boolean('can_download', true),
                    'can_edit' => $request->boolean('can_edit', false),
                    'can_delete' => $request->boolean('can_delete', false),
                    'can_share' => $request->boolean('can_share', false),
                ]
            );

            AuditLogService::log('folder.permission_updated', 'Folder', $id, [
                'target_user_id' => $targetUserId,
                'permissions' => $perm->toArray(),
            ]);
        } else {
            $file = File::findOrFail($id);
            $this->authorize('share', $file);

            $perm = FileUserPermission::updateOrCreate(
                ['file_id' => $id, 'user_id' => $targetUserId],
                [
                    'can_view' => $request->boolean('can_view', true),
                    'can_download' => $request->boolean('can_download', true),
                    'can_edit' => $request->boolean('can_edit', false),
                    'can_delete' => $request->boolean('can_delete', false),
                    'can_share' => $request->boolean('can_share', false),
                ]
            );

            AuditLogService::log('file.permission_updated', 'File', $id, [
                'target_user_id' => $targetUserId,
                'permissions' => $perm->toArray(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully.',
            'permission' => $perm->load('user:id,name,email'),
        ]);
    }

    /**
     * Revoke user permission.
     */
    public function revokePermission(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:file,folder',
            'id' => 'required|integer',
            'user_id' => 'required|exists:users,id',
        ]);

        $type = $request->type;
        $id = (int) $request->id;
        $targetUserId = (int) $request->user_id;

        if ($type === 'folder') {
            $folder = Folder::findOrFail($id);
            $this->authorize('share', $folder);

            FolderUserPermission::where('folder_id', $id)->where('user_id', $targetUserId)->delete();
            AuditLogService::log('folder.permission_revoked', 'Folder', $id, ['target_user_id' => $targetUserId]);
        } else {
            $file = File::findOrFail($id);
            $this->authorize('share', $file);

            FileUserPermission::where('file_id', $id)->where('user_id', $targetUserId)->delete();
            AuditLogService::log('file.permission_revoked', 'File', $id, ['target_user_id' => $targetUserId]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission revoked successfully.',
        ]);
    }
}
