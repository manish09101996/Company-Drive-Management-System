<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Models\ImportItem;
use App\Models\ImportJob;
use App\Models\Role;
use App\Services\AuditLogService;
use App\Services\PrivateStorageService;
use App\Services\TakeoutImportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TakeoutImportController extends Controller
{
    protected TakeoutImportService $importService;
    protected PrivateStorageService $storageService;

    public function __construct(TakeoutImportService $importService, PrivateStorageService $storageService)
    {
        $this->importService = $importService;
        $this->storageService = $storageService;
    }

    /**
     * Import Center Dashboard.
     */
    public function index(): View
    {
        $this->authorize('viewAny', ImportJob::class);

        $jobs = ImportJob::with(['creator', 'destinationFolder'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        $storageStats = $this->storageService->getStorageStats();
        $folders = Folder::orderBy('name')->get(['id', 'name', 'parent_id', 'uuid']);
        $roles = Role::orderBy('name')->get();

        $existingZipFiles = File::where('extension', 'zip')
            ->orWhere('name', 'like', '%.zip')
            ->with(['folder', 'uploader'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($file) {
                $file->import_job = ImportJob::where('archive_path', $file->storage_path)->latest()->first();
                return $file;
            });

        return view('imports.index', compact('jobs', 'storageStats', 'folders', 'roles', 'existingZipFiles'));
    }

    /**
     * Handle resumable chunk upload for large ZIP archives.
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        $this->authorize('create', ImportJob::class);

        $request->validate([
            'chunk' => 'required|file',
            'upload_id' => 'required|string',
            'chunk_index' => 'required|integer',
            'total_chunks' => 'required|integer',
            'original_filename' => 'required|string',
        ]);

        try {
            $result = $this->importService->handleChunkUpload(
                $request->file('chunk'),
                $request->upload_id,
                (int) $request->chunk_index,
                (int) $request->total_chunks,
                $request->original_filename
            );

            if ($result['complete']) {
                $user = Auth::user();

                $job = ImportJob::create([
                    'uuid' => $result['job_uuid'],
                    'title' => 'Google Takeout - ' . pathinfo($result['archive_filename'], PATHINFO_FILENAME),
                    'source_type' => 'takeout_zip',
                    'archive_filename' => $result['archive_filename'],
                    'archive_path' => $result['archive_path'],
                    'archive_size_bytes' => $result['archive_size_bytes'],
                    'status' => 'pending',
                    'created_by' => $user->id,
                ]);

                AuditLogService::log('import.archive_uploaded', 'ImportJob', $job->id, [
                    'filename' => $job->archive_filename,
                    'size_bytes' => $job->archive_size_bytes,
                ]);

                return response()->json([
                    'success' => true,
                    'complete' => true,
                    'job_uuid' => $job->uuid,
                    'message' => 'Archive uploaded and reassembled successfully.',
                ]);
            }

            return response()->json([
                'success' => true,
                'complete' => false,
                'received' => $result['received'],
                'total' => $result['total'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Inspect archive and generate manifest with security checks.
     */
    public function inspect(ImportJob $job): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $job);

        try {
            $inspection = $this->importService->inspectArchive($job);

            AuditLogService::log('import.inspected', 'ImportJob', $job->id, [
                'total_entries' => $inspection['total_entries'],
                'total_files' => $inspection['total_files'],
                'total_folders' => $inspection['total_folders'],
            ]);

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'inspection' => $inspection,
                ]);
            }

            return redirect()->route('imports.show', $job->uuid)->with('success', 'Archive inspected and manifest generated.');
        } catch (Exception $e) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Manifest preview and configuration screen.
     */
    public function show(string $uuid): View
    {
        $job = ImportJob::where('uuid', $uuid)
            ->with(['destinationFolder', 'creator'])
            ->firstOrFail();

        $this->authorize('view', $job);

        $itemsPreview = ImportItem::where('import_job_id', $job->id)
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        $folders = Folder::orderBy('name')->get(['id', 'name', 'parent_id', 'uuid']);
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        $storageStats = $this->storageService->getStorageStats();

        return view('imports.show', compact('job', 'itemsPreview', 'folders', 'departments', 'roles', 'storageStats'));
    }

    /**
     * Start/trigger the import extraction job.
     */
    public function start(Request $request, ImportJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $request->validate([
            'destination_folder_id' => 'nullable|exists:folders,id',
            'default_access_role' => 'nullable|string',
            'duplicate_action' => 'nullable|in:skip,rename,overwrite',
            'delete_source_zip' => 'nullable|boolean',
        ]);

        $settings = $job->settings ?? [];
        $settings['duplicate_action'] = $request->input('duplicate_action', 'skip');
        $settings['delete_source_zip'] = $request->boolean('delete_source_zip');

        $job->update([
            'destination_folder_id' => $request->destination_folder_id ?: null,
            'default_access_role' => $request->default_access_role ?: 'Employee',
            'settings' => $settings,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        AuditLogService::log('import.started', 'ImportJob', $job->id, [
            'destination_folder_id' => $job->destination_folder_id,
            'duplicate_action' => $settings['duplicate_action'],
            'delete_source_zip' => $settings['delete_source_zip'],
        ]);

        return redirect()->route('imports.show', $job->uuid)->with('success', 'Import extraction started.');
    }

    /**
     * Update selection of items to extract (select/deselect individual items, all, or folders).
     */
    public function updateSelection(Request $request, ImportJob $job): JsonResponse
    {
        $this->authorize('update', $job);

        $request->validate([
            'action' => 'required|in:select_all,deselect_all,custom,toggle',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:import_items,id',
            'item_id' => 'nullable|integer|exists:import_items,id',
            'status' => 'nullable|in:pending,skipped',
        ]);

        $action = $request->input('action');

        if ($action === 'select_all') {
            ImportItem::where('import_job_id', $job->id)
                ->where('status', '!=', 'completed')
                ->update(['status' => 'pending']);
        } elseif ($action === 'deselect_all') {
            ImportItem::where('import_job_id', $job->id)
                ->where('status', '!=', 'completed')
                ->update(['status' => 'skipped']);
        } elseif ($action === 'custom') {
            $selectedIds = $request->input('item_ids', []);
            ImportItem::where('import_job_id', $job->id)
                ->where('status', '!=', 'completed')
                ->whereIn('id', $selectedIds)
                ->update(['status' => 'pending']);
            ImportItem::where('import_job_id', $job->id)
                ->where('status', '!=', 'completed')
                ->whereNotIn('id', $selectedIds)
                ->update(['status' => 'skipped']);
        } elseif ($action === 'toggle') {
            $itemId = $request->input('item_id');
            $newStatus = $request->input('status', 'pending');
            ImportItem::where('import_job_id', $job->id)
                ->where('id', $itemId)
                ->where('status', '!=', 'completed')
                ->update(['status' => $newStatus]);
        }

        $pendingCount = ImportItem::where('import_job_id', $job->id)->where('status', 'pending')->count();
        $skippedCount = ImportItem::where('import_job_id', $job->id)->where('status', 'skipped')->count();
        $completedCount = ImportItem::where('import_job_id', $job->id)->where('status', 'completed')->count();
        $failedCount = ImportItem::where('import_job_id', $job->id)->where('status', 'failed')->count();

        $job->update(['skipped_entries' => $skippedCount]);

        return response()->json([
            'success' => true,
            'pending' => $pendingCount,
            'skipped' => $skippedCount,
            'completed' => $completedCount,
            'failed' => $failedCount,
            'total' => $job->total_entries,
        ]);
    }

    /**
     * Get paginated and filterable manifest items via AJAX.
     */
    public function getItems(Request $request, ImportJob $job): JsonResponse
    {
        $this->authorize('view', $job);

        $query = ImportItem::where('import_job_id', $job->id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('entry_name', 'like', "%{$search}%")
                  ->orWhere('entry_path', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('entry_type', $request->input('type'));
        }

        $items = $query->orderBy('id', 'asc')->paginate(100);

        return response()->json($items);
    }

    /**
     * Controlled batch execution endpoint (called iteratively via AJAX runner).
     */
    public function processBatch(Request $request, ImportJob $job): JsonResponse
    {
        $this->authorize('update', $job);

        $batchSize = $request->input('batch_size', 50);

        try {
            $result = $this->importService->processBatch($job, (int) $batchSize, Auth::user());
            return response()->json(array_merge(['success' => true], $result));
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry failed items in the import job.
     */
    public function retryFailed(ImportJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        ImportItem::where('import_job_id', $job->id)
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'error_message' => null,
            ]);

        $job->update(['status' => 'processing']);

        AuditLogService::log('import.retry_initiated', 'ImportJob', $job->id);

        return back()->with('success', 'Failed items have been queued for retry.');
    }

    /**
     * Cancel an ongoing import job.
     */
    public function cancel(ImportJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->update(['status' => 'cancelled']);
        AuditLogService::log('import.cancelled', 'ImportJob', $job->id);

        return back()->with('success', 'Import job has been cancelled.');
    }

    /**
     * Delete an import job and its archive file.
     */
    public function destroy(ImportJob $job): RedirectResponse
    {
        $this->authorize('delete', $job);

        $archivePath = storage_path("app/private/{$job->archive_path}");
        if (file_exists($archivePath)) {
            @unlink($archivePath);
        }

        AuditLogService::log('import.deleted', 'ImportJob', $job->id, ['title' => $job->title]);

        $job->delete();

        return redirect()->route('imports.index')->with('success', 'Import job and archive removed.');
    }
}
