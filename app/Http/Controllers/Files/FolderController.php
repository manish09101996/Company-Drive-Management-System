<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\DeletedItem;
use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FolderController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * File Manager view: list current folder contents (or root).
     */
    public function index(Request $request, ?string $uuid = null): View
    {
        $user = Auth::user();
        $currentFolder = null;
        $breadcrumbs = [];

        if ($uuid) {
            $currentFolder = Folder::where('uuid', $uuid)->firstOrFail();
            $this->authorize('view', $currentFolder);
            $breadcrumbs = $currentFolder->getBreadcrumbs();
        } else {
            if (!$user->isSuperAdmin() && !$user->hasPermission('files.view')) {
                abort(403, 'Unauthorized to view files.');
            }
        }

        // File type filter closure for eager loading on folders
        $fileTypeFilter = function ($q) use ($request) {
            if ($request->filled('type')) {
                $type = $request->type;
                if ($type === 'images') {
                    $q->whereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']);
                } elseif ($type === 'documents') {
                    $q->whereIn('extension', ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx']);
                } elseif ($type === 'archives') {
                    $q->whereIn('extension', ['zip', 'rar', 'tar', 'gz', '7z']);
                } elseif ($type === 'media') {
                    $q->whereIn('extension', ['mp4', 'mov', 'mp3', 'wav', 'avi']);
                }
            }
            $q->with(['uploader', 'department'])->orderBy('name', 'asc');
        };

        // Subfolders and Files queries
        if ($request->filled('search')) {
            $search = trim($request->search);
            if ($currentFolder) {
                // When searching inside a folder, search this folder and all its descendants
                $descendantFolderIds = $this->getAllDescendantFolderIds($currentFolder);
                $allowedFolderIds = array_merge([$currentFolder->id], $descendantFolderIds);

                $foldersQuery = Folder::whereIn('parent_id', $allowedFolderIds)
                    ->where('name', 'like', "%{$search}%")
                    ->with(['department', 'creator', 'assignedUser', 'files' => $fileTypeFilter]);

                $filesQuery = File::whereIn('folder_id', $allowedFolderIds)
                    ->where('name', 'like', "%{$search}%")
                    ->with(['uploader', 'department', 'folder']);
            } else {
                // Global search across drive
                if ($user->isSuperAdmin()) {
                    $foldersQuery = Folder::where('name', 'like', "%{$search}%")
                        ->with(['department', 'creator', 'assignedUser', 'files' => $fileTypeFilter]);

                    $filesQuery = File::where('name', 'like', "%{$search}%")
                        ->with(['uploader', 'department', 'folder']);
                } else {
                    // Non-superadmin: strictly limit search to their assigned folders and descendants
                    $assignedFolderIds = Folder::where('assigned_user_id', $user->id)->pluck('id')->toArray();
                    $allAllowedFolderIds = [];
                    foreach ($assignedFolderIds as $afid) {
                        $afModel = Folder::find($afid);
                        if ($afModel) {
                            $allAllowedFolderIds = array_merge($allAllowedFolderIds, [$afid], $this->getAllDescendantFolderIds($afModel));
                        }
                    }

                    $foldersQuery = Folder::whereIn('id', $allAllowedFolderIds)
                        ->where('name', 'like', "%{$search}%")
                        ->with(['department', 'creator', 'assignedUser', 'files' => $fileTypeFilter]);

                    $filesQuery = File::whereIn('folder_id', $allAllowedFolderIds)
                        ->where('name', 'like', "%{$search}%")
                        ->with(['uploader', 'department', 'folder']);
                }
            }
        } else {
            // Standard directory listing of current folder level
            if ($currentFolder) {
                $foldersQuery = Folder::where('parent_id', $currentFolder->id)
                    ->with(['department', 'creator', 'assignedUser', 'files' => $fileTypeFilter]);

                $filesQuery = File::where('folder_id', $currentFolder->id)
                    ->with(['uploader', 'department', 'folder']);
            } else {
                // Root level:
                if ($user->isSuperAdmin()) {
                    // Super Admin sees all five main folders
                    $foldersQuery = Folder::whereNull('parent_id')
                        ->with(['department', 'creator', 'assignedUser', 'files' => $fileTypeFilter]);

                    $filesQuery = File::whereNull('folder_id')
                        ->with(['uploader', 'department', 'folder']);
                } else {
                    // Employees see ONLY their assigned folder
                    $foldersQuery = Folder::whereNull('parent_id')
                        ->where('assigned_user_id', $user->id)
                        ->with(['department', 'creator', 'assignedUser', 'files' => $fileTypeFilter]);

                    $filesQuery = File::whereRaw('1 = 0')
                        ->with(['uploader', 'department', 'folder']);
                }
            }
        }

        // Filter by department
        if ($request->filled('department_id')) {
            $foldersQuery->where('department_id', $request->department_id);
            $filesQuery->where('department_id', $request->department_id);
        }

        // Filter by file type
        if ($request->filled('type')) {
            $type = $request->type;
            if ($type === 'images') {
                $filesQuery->whereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']);
            } elseif ($type === 'documents') {
                $filesQuery->whereIn('extension', ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx']);
            } elseif ($type === 'archives') {
                $filesQuery->whereIn('extension', ['zip', 'rar', 'tar', 'gz', '7z']);
            } elseif ($type === 'media') {
                $filesQuery->whereIn('extension', ['mp4', 'mov', 'mp3', 'wav', 'avi']);
            }
        }

        // Sorting
        $sortBy = $request->get('sort', 'name');
        $sortOrder = $request->get('order', 'asc');
        if (in_array($sortBy, ['name', 'created_at', 'size_bytes'])) {
            if ($sortBy === 'size_bytes') {
                $filesQuery->orderBy('size_bytes', $sortOrder);
                $foldersQuery->orderBy('name', 'asc');
            } else {
                $foldersQuery->orderBy($sortBy, $sortOrder);
                $filesQuery->orderBy($sortBy, $sortOrder);
            }
        }

        $allFolders = $foldersQuery->get();
        $allFiles = $filesQuery->get();

        // Server-side permission filter for non-superadmins
        $accessibleFolders = $allFolders->filter(function ($folder) use ($user) {
            return $this->permissionService->canAccessFolder($user, $folder, 'view');
        })->map(function ($folder) use ($user) {
            if (!$user->isSuperAdmin()) {
                $permittedFiles = $folder->files->filter(function ($file) use ($user) {
                    return $this->permissionService->canAccessFile($user, $file, 'view');
                });
                $folder->setRelation('files', $permittedFiles);
            }
            return $folder;
        });

        $accessibleFiles = $allFiles->filter(function ($file) use ($user) {
            return $this->permissionService->canAccessFile($user, $file, 'view');
        });

        $departments = Department::where('is_active', true)->orderBy('name')->get();

        // System folders list for Move modal: restricted to accessible hierarchy for non-superadmins
        if ($user->isSuperAdmin()) {
            $allSystemFolders = Folder::orderBy('name')->get(['id', 'name', 'parent_id', 'uuid']);
        } else {
            $assignedFolderIds = Folder::where('assigned_user_id', $user->id)->pluck('id')->toArray();
            $allAllowedFolderIds = [];
            foreach ($assignedFolderIds as $afid) {
                $afModel = Folder::find($afid);
                if ($afModel) {
                    $allAllowedFolderIds = array_merge($allAllowedFolderIds, [$afid], $this->getAllDescendantFolderIds($afModel));
                }
            }
            $allSystemFolders = Folder::whereIn('id', $allAllowedFolderIds)->orderBy('name')->get(['id', 'name', 'parent_id', 'uuid']);
        }

        $allUsers = User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);

        return view('files.index', [
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $breadcrumbs,
            'folders' => $accessibleFolders,
            'files' => $accessibleFiles,
            'departments' => $departments,
            'allSystemFolders' => $allSystemFolders,
            'allUsers' => $allUsers,
        ]);
    }

    /**
     * Create a new folder.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $parentId = $request->filled('parent_id') ? (int) $request->parent_id : null;
        $parentFolder = $parentId ? Folder::find($parentId) : null;

        $this->authorize('create', [Folder::class, $parentFolder]);

        $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'color' => 'nullable|string|max:20',
        ]);

        // Inherit assigned_user_id from parent folder
        $assignedUserId = $parentFolder ? $parentFolder->getEffectiveAssignedUserId() : null;
        if ($user->isSuperAdmin() && $request->filled('assigned_user_id')) {
            $assignedUserId = (int) $request->assigned_user_id;
        }

        $folder = Folder::create([
            'uuid' => (string) Str::uuid(),
            'name' => trim($request->name),
            'parent_id' => $parentId,
            'department_id' => $request->department_id ?: $parentFolder?->department_id,
            'created_by' => $user->id,
            'assigned_user_id' => $assignedUserId,
            'color' => $request->color ?: '#4f46e5',
        ]);

        AuditLogService::log('folder.created', 'Folder', $folder->id, [
            'name' => $folder->name,
            'parent_id' => $parentId,
            'assigned_user_id' => $assignedUserId,
        ]);

        return back()->with('success', "Folder '{$folder->name}' created successfully.");
    }

    /**
     * Rename a folder.
     */
    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $user = Auth::user();
        $this->authorize('update', $folder);

        if ($folder->parent_id === null && !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized to rename the main user folder.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:20',
        ]);

        $oldName = $folder->name;
        $folder->update([
            'name' => trim($request->name),
            'color' => $request->color ?: $folder->color,
        ]);

        AuditLogService::log('folder.renamed', 'Folder', $folder->id, [
            'old_name' => $oldName,
            'new_name' => $folder->name,
        ]);

        return back()->with('success', "Folder renamed to '{$folder->name}'.");
    }

    /**
     * Move a folder to another parent.
     */
    public function move(Request $request, Folder $folder): RedirectResponse
    {
        $user = Auth::user();
        $this->authorize('update', $folder);

        if ($folder->parent_id === null && !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized to move the main user folder.');
        }

        $request->validate([
            'target_folder_id' => 'nullable|exists:folders,id',
        ]);

        $targetFolderId = $request->target_folder_id ? (int) $request->target_folder_id : null;

        if ($targetFolderId === $folder->id) {
            return back()->withErrors(['error' => 'Cannot move a folder into itself.']);
        }

        if ($targetFolderId) {
            $targetFolder = Folder::find($targetFolderId);
            if (!$targetFolder || !$this->permissionService->canAccessFolder($user, $targetFolder, 'upload')) {
                abort(403, 'Unauthorized to move into the destination folder.');
            }
        } elseif (!$user->isSuperAdmin()) {
            abort(403, 'Only Super Admin can move folders to the root level.');
        }

        $folder->update(['parent_id' => $targetFolderId]);

        AuditLogService::log('folder.moved', 'Folder', $folder->id, [
            'new_parent_id' => $targetFolderId,
        ]);

        return back()->with('success', "Folder moved successfully.");
    }

    /**
     * Move folder to recycle bin (soft delete).
     */
    public function destroy(Folder $folder): RedirectResponse
    {
        $user = Auth::user();
        $this->authorize('delete', $folder);

        if ($folder->parent_id === null && !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized to delete the main user folder.');
        }

        $folderName = $folder->name;
        $originalParentId = $folder->parent_id;

        DeletedItem::create([
            'item_type' => 'folder',
            'item_id' => $folder->id,
            'original_parent_id' => $originalParentId,
            'original_name' => $folderName,
            'size_bytes' => $folder->calculateTotalSize(),
            'deleted_by' => $user->id,
            'deleted_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $folder->delete();

        AuditLogService::log('folder.deleted_to_recycle_bin', 'Folder', $folder->id, [
            'name' => $folderName,
        ]);

        return back()->with('success', "Folder '{$folderName}' moved to Recycle Bin.");
    }

    /**
     * Get all descendant subfolder IDs iteratively.
     */
    public function getAllDescendantFolderIds(Folder $folder): array
    {
        $allIds = [];
        $queue = [$folder->id];

        while (!empty($queue)) {
            $currentParentId = array_shift($queue);
            $childIds = Folder::where('parent_id', $currentParentId)->pluck('id')->toArray();
            foreach ($childIds as $id) {
                $allIds[] = $id;
                $queue[] = $id;
            }
        }

        return $allIds;
    }
}
