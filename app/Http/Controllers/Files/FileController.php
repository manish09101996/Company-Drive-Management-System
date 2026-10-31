<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\DeletedItem;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\ImportJob;
use App\Services\AuditLogService;
use App\Services\PermissionService;
use App\Services\PrivateStorageService;
use App\Services\TakeoutImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    protected PrivateStorageService $storageService;
    protected PermissionService $permissionService;

    public function __construct(PrivateStorageService $storageService, PermissionService $permissionService)
    {
        $this->storageService = $storageService;
        $this->permissionService = $permissionService;
    }

    /**
     * Resumable chunk upload for large files in File Manager.
     */
    public function chunkUpload(Request $request): JsonResponse
    {
        $user = Auth::user();
        $folderId = $request->filled('folder_id') ? (int) $request->folder_id : null;
        $folder = $folderId ? Folder::find($folderId) : null;

        if (!$this->permissionService->canAccessFolder($user, $folder, 'upload')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to upload to this folder.'], 403);
        }

        $request->validate([
            'chunk' => 'required|file',
            'upload_id' => 'required|string',
            'chunk_index' => 'required|integer',
            'total_chunks' => 'required|integer',
            'original_filename' => 'required|string',
            'folder_id' => 'nullable|exists:folders,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->upload_id);
        $tempDir = storage_path("app/private/temp_chunks/{$uploadId}");
        if (!file_exists($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $chunkIndex = (int) $request->chunk_index;
        $totalChunks = (int) $request->total_chunks;
        $chunkFile = $request->file('chunk');
        $chunkFile->move($tempDir, "chunk_{$chunkIndex}.part");

        $receivedChunks = glob("{$tempDir}/chunk_*.part");
        if (count($receivedChunks) === $totalChunks) {
            $extension = strtolower(pathinfo($request->original_filename, PATHINFO_EXTENSION));
            $yearMonth = date('Y/m');
            $randomFileName = Str::random(40) . ($extension ? '.' . $extension : '.bin');
            $storageRelPath = "files/{$yearMonth}/{$randomFileName}";
            $fullStoragePath = storage_path("app/private/{$storageRelPath}");

            $dir = dirname($fullStoragePath);
            if (!file_exists($dir)) {
                @mkdir($dir, 0755, true);
            }

            $outHandle = fopen($fullStoragePath, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $partPath = "{$tempDir}/chunk_{$i}.part";
                if (!file_exists($partPath)) {
                    fclose($outHandle);
                    return response()->json(['success' => false, 'message' => "Missing chunk {$i} during reassembly."], 500);
                }
                $inHandle = fopen($partPath, 'rb');
                while (!feof($inHandle)) {
                    fwrite($outHandle, fread($inHandle, 1024 * 64));
                }
                fclose($inHandle);
                @unlink($partPath);
            }
            fclose($outHandle);
            @rmdir($tempDir);

            $sizeBytes = file_exists($fullStoragePath) ? filesize($fullStoragePath) : 0;
            $checksum = file_exists($fullStoragePath) ? hash_file('sha256', $fullStoragePath) : null;
            $mimeType = @mime_content_type($fullStoragePath) ?: 'application/octet-stream';

            $deptId = $request->department_id ?: $folder?->department_id;

            $fileModel = File::create([
                'uuid' => (string) Str::uuid(),
                'name' => $request->original_filename,
                'folder_id' => $folderId,
                'department_id' => $deptId,
                'storage_disk' => 'private',
                'storage_path' => $storageRelPath,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $sizeBytes,
                'checksum_sha256' => $checksum,
                'uploaded_by' => $user->id,
            ]);

            FileVersion::create([
                'file_id' => $fileModel->id,
                'version_number' => 1,
                'storage_disk' => 'private',
                'storage_path' => $storageRelPath,
                'size_bytes' => $sizeBytes,
                'checksum_sha256' => $checksum,
                'uploaded_by' => $user->id,
                'change_summary' => 'Initial chunked upload',
            ]);

            AuditLogService::log('file.uploaded', 'File', $fileModel->id, [
                'name' => $fileModel->name,
                'size_bytes' => $fileModel->size_bytes,
                'checksum_sha256' => $fileModel->checksum_sha256,
            ]);

            return response()->json([
                'success' => true,
                'complete' => true,
                'message' => 'File uploaded and verified successfully.',
                'file' => $fileModel,
            ]);
        }

        return response()->json([
            'success' => true,
            'complete' => false,
            'received' => count($receivedChunks),
            'total' => $totalChunks,
        ]);
    }

    /**
     * Upload single or multiple files.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $folderId = $request->filled('folder_id') ? (int) $request->folder_id : null;
        $folder = $folderId ? Folder::find($folderId) : null;

        if (!$this->permissionService->canAccessFolder($user, $folder, 'upload')) {
            abort(403, 'You do not have permission to upload files to this location.');
        }

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:524288', // 512MB max per single file
            'folder_id' => 'nullable|exists:folders,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $uploadedRecords = [];
        $deptId = $request->department_id ?: $folder?->department_id;

        foreach ($request->file('files') as $uploadedFile) {
            $stored = $this->storageService->storeUploadedFile($uploadedFile, 'files');

            $fileModel = File::create([
                'uuid' => (string) Str::uuid(),
                'name' => $stored['name'],
                'folder_id' => $folderId,
                'department_id' => $deptId,
                'storage_disk' => $stored['storage_disk'],
                'storage_path' => $stored['storage_path'],
                'mime_type' => $stored['mime_type'],
                'extension' => $stored['extension'],
                'size_bytes' => $stored['size_bytes'],
                'checksum_sha256' => $stored['checksum_sha256'],
                'uploaded_by' => $user->id,
            ]);

            // Create initial version 1
            FileVersion::create([
                'file_id' => $fileModel->id,
                'version_number' => 1,
                'storage_disk' => $stored['storage_disk'],
                'storage_path' => $stored['storage_path'],
                'size_bytes' => $stored['size_bytes'],
                'checksum_sha256' => $stored['checksum_sha256'],
                'uploaded_by' => $user->id,
                'change_summary' => 'Initial upload',
            ]);

            AuditLogService::log('file.uploaded', 'File', $fileModel->id, [
                'name' => $fileModel->name,
                'size_bytes' => $fileModel->size_bytes,
                'checksum_sha256' => $fileModel->checksum_sha256,
            ]);

            $uploadedRecords[] = $fileModel;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'count' => count($uploadedRecords),
                'message' => count($uploadedRecords) . ' file(s) uploaded successfully.',
            ]);
        }

        return back()->with('success', count($uploadedRecords) . ' file(s) uploaded successfully.');
    }

    /**
     * Download a private file securely.
     */
    public function download(File $file): StreamedResponse
    {
        $this->authorize('download', $file);

        AuditLogService::log('file.downloaded', 'File', $file->id, [
            'name' => $file->name,
            'size_bytes' => $file->size_bytes,
        ]);

        return $this->storageService->streamFile($file, false);
    }

    /**
     * Stream a file inline for previewing.
     */
    public function preview(File $file): StreamedResponse
    {
        $this->authorize('view', $file);

        AuditLogService::log('file.previewed', 'File', $file->id, [
            'name' => $file->name,
        ]);

        return $this->storageService->streamFile($file, true);
    }

    /**
     * Rename a file.
     */
    public function update(Request $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $oldName = $file->name;
        $newName = trim($request->name);

        // Keep extension if user didn't specify one
        $newExt = pathinfo($newName, PATHINFO_EXTENSION);
        if (empty($newExt) && $file->extension) {
            $newName .= '.' . $file->extension;
        }

        $file->update([
            'name' => $newName,
            'extension' => strtolower(pathinfo($newName, PATHINFO_EXTENSION)),
        ]);

        AuditLogService::log('file.renamed', 'File', $file->id, [
            'old_name' => $oldName,
            'new_name' => $file->name,
        ]);

        return back()->with('success', "File renamed to '{$file->name}'.");
    }

    /**
     * Move a file to another folder.
     */
    public function move(Request $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $request->validate([
            'target_folder_id' => 'nullable|exists:folders,id',
        ]);

        $targetFolderId = $request->target_folder_id ? (int) $request->target_folder_id : null;
        if ($targetFolderId) {
            $targetFolder = Folder::find($targetFolderId);
            if (!$targetFolder || !$this->permissionService->canAccessFolder($user, $targetFolder, 'upload')) {
                abort(403, 'Unauthorized to move file to the destination folder.');
            }
        } elseif (!$user->isSuperAdmin()) {
            abort(403, 'Only Super Admin can move files to the root level.');
        }

        $file->update(['folder_id' => $targetFolderId]);

        AuditLogService::log('file.moved', 'File', $file->id, [
            'new_folder_id' => $targetFolderId,
        ]);

        return back()->with('success', "File moved successfully.");
    }

    /**
     * Move file to recycle bin (soft delete).
     */
    public function destroy(File $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        $user = Auth::user();
        $fileName = $file->name;
        $originalParentId = $file->folder_id;

        DeletedItem::create([
            'item_type' => 'file',
            'item_id' => $file->id,
            'original_parent_id' => $originalParentId,
            'original_name' => $fileName,
            'size_bytes' => $file->size_bytes,
            'deleted_by' => $user->id,
            'deleted_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $file->delete();

        AuditLogService::log('file.deleted_to_recycle_bin', 'File', $file->id, [
            'name' => $fileName,
        ]);

        return back()->with('success', "File '{$fileName}' moved to Recycle Bin.");
    }

    /**
     * Upload a new version of an existing file.
     */
    public function uploadVersion(Request $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $request->validate([
            'version_file' => 'required|file|max:524288',
            'change_summary' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $stored = $this->storageService->storeUploadedFile($request->file('version_file'), 'files');

        $latestVersionNumber = (int) $file->versions()->max('version_number');
        $newVersionNumber = $latestVersionNumber + 1;

        FileVersion::create([
            'file_id' => $file->id,
            'version_number' => $newVersionNumber,
            'storage_disk' => $stored['storage_disk'],
            'storage_path' => $stored['storage_path'],
            'size_bytes' => $stored['size_bytes'],
            'checksum_sha256' => $stored['checksum_sha256'],
            'uploaded_by' => $user->id,
            'change_summary' => $request->change_summary ?: "Uploaded version {$newVersionNumber}",
        ]);

        // Update active file attributes
        $file->update([
            'storage_disk' => $stored['storage_disk'],
            'storage_path' => $stored['storage_path'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'checksum_sha256' => $stored['checksum_sha256'],
        ]);

        AuditLogService::log('file.version_uploaded', 'File', $file->id, [
            'version_number' => $newVersionNumber,
            'size_bytes' => $stored['size_bytes'],
        ]);

        return back()->with('success', "Version {$newVersionNumber} uploaded successfully.");
    }

    /**
     * Revert file to a previous version.
     */
    public function restoreVersion(File $file, FileVersion $version): RedirectResponse
    {
        $this->authorize('update', $file);

        if ($version->file_id !== $file->id) {
            abort(404);
        }

        $file->update([
            'storage_disk' => $version->storage_disk,
            'storage_path' => $version->storage_path,
            'size_bytes' => $version->size_bytes,
            'checksum_sha256' => $version->checksum_sha256,
        ]);

        AuditLogService::log('file.version_restored', 'File', $file->id, [
            'restored_version_number' => $version->version_number,
        ]);

        return back()->with('success', "File restored to version {$version->version_number}.");
    }

    /**
     * Toggle favorite status.
     */
    public function toggleFavorite(File $file): JsonResponse
    {
        $this->authorize('view', $file);

        $file->update(['is_favorite' => !$file->is_favorite]);

        return response()->json([
            'success' => true,
            'is_favorite' => $file->is_favorite,
        ]);
    }

    /**
     * Initiate extraction of a ZIP archive file directly from Company Drive.
     */
    public function extractZip(File $file, TakeoutImportService $importService): RedirectResponse
    {
        $this->authorize('update', $file);

        $ext = strtolower($file->extension ?: pathinfo($file->name, PATHINFO_EXTENSION));
        if ($ext !== 'zip' && !str_contains(strtolower($file->mime_type), 'zip')) {
            return back()->withErrors(['error' => 'The selected file is not a valid ZIP archive.']);
        }

        $fullPath = storage_path("app/private/{$file->storage_path}");
        if (!file_exists($fullPath)) {
            return back()->withErrors(['error' => 'Archive file not found in storage.']);
        }

        $user = Auth::user();

        // Check if there is an existing import job for this file
        $job = ImportJob::where('archive_path', $file->storage_path)
            ->whereIn('status', ['pending', 'manifest_ready', 'processing', 'partially_completed'])
            ->first();

        if (!$job) {
            $job = ImportJob::create([
                'uuid' => (string) Str::uuid(),
                'title' => 'Extract: ' . $file->name,
                'source_type' => 'drive_file',
                'archive_filename' => $file->name,
                'archive_path' => $file->storage_path,
                'archive_size_bytes' => $file->size_bytes,
                'destination_folder_id' => $file->folder_id,
                'default_access_role' => 'Employee',
                'status' => 'pending',
                'created_by' => $user->id,
                'settings' => [
                    'source_file_id' => $file->id,
                    'delete_source_zip' => false,
                    'duplicate_action' => 'skip',
                ],
            ]);
        }

        // Generate manifest and inspect archive entries if not yet ready
        if ($job->status === 'pending' || $job->total_entries === 0) {
            try {
                $importService->inspectArchive($job);
            } catch (\Exception $e) {
                return redirect()->route('imports.show', $job->uuid)->withErrors(['error' => 'Archive inspection warning: ' . $e->getMessage()]);
            }
        }

        AuditLogService::log('file.archive_extraction_opened', 'File', $file->id, [
            'import_job_uuid' => $job->uuid,
            'filename' => $file->name,
        ]);

        return redirect()->route('imports.show', $job->uuid)->with('success', "Archive \"{$file->name}\" inspected successfully. Ready for extraction.");
    }
}
