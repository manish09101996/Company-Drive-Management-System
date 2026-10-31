<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\DeletedItem;
use App\Models\File;
use App\Models\Folder;
use App\Services\AuditLogService;
use App\Services\PrivateStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RecycleBinController extends Controller
{
    protected PrivateStorageService $storageService;

    public function __construct(PrivateStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('files.delete')) {
            abort(403);
        }

        $query = DeletedItem::with('deleter');

        if ($request->filled('type')) {
            $query->where('item_type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where('original_name', 'like', "%{$request->search}%");
        }

        $items = $query->orderBy('deleted_at', 'desc')->paginate(20)->withQueryString();
        $totalItemsCount = DeletedItem::count();
        $totalBytesInBin = DeletedItem::sum('size_bytes');

        return view('files.recycle_bin', [
            'items' => $items,
            'totalItemsCount' => $totalItemsCount,
            'totalBytesFormatted' => $this->storageService->formatBytes($totalBytesInBin),
        ]);
    }

    /**
     * Restore item from recycle bin.
     */
    public function restore(DeletedItem $item): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('files.restore')) {
            abort(403);
        }

        if ($item->item_type === 'file') {
            $file = File::withTrashed()->find($item->item_id);
            if ($file) {
                // Check if original parent folder exists; if not, restore to root
                if ($file->folder_id && !Folder::where('id', $file->folder_id)->exists()) {
                    $file->folder_id = null;
                }
                $file->restore();
            }
        } elseif ($item->item_type === 'folder') {
            $folder = Folder::withTrashed()->find($item->item_id);
            if ($folder) {
                if ($folder->parent_id && !Folder::where('id', $folder->parent_id)->exists()) {
                    $folder->parent_id = null;
                }
                $folder->restore();
            }
        }

        $itemName = $item->original_name;
        $item->delete();

        AuditLogService::log('recycle_bin.restored', ucfirst($item->item_type), $item->item_id, [
            'name' => $itemName,
        ]);

        return back()->with('success', "'{$itemName}' has been restored successfully.");
    }

    /**
     * Permanently purge single item.
     */
    public function forceDelete(DeletedItem $item): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('files.delete')) {
            abort(403);
        }

        $itemName = $item->original_name;

        if ($item->item_type === 'file') {
            $file = File::withTrashed()->find($item->item_id);
            if ($file) {
                $this->storageService->deletePhysicalFile($file->storage_path, $file->storage_disk);
                $file->forceDelete();
            }
        } elseif ($item->item_type === 'folder') {
            $folder = Folder::withTrashed()->find($item->item_id);
            if ($folder) {
                $folder->forceDelete();
            }
        }

        $item->delete();

        AuditLogService::log('recycle_bin.permanently_deleted', ucfirst($item->item_type), $item->item_id, [
            'name' => $itemName,
        ]);

        return back()->with('success', "'{$itemName}' has been permanently deleted.");
    }

    /**
     * Permanently empty entire recycle bin.
     */
    public function empty(): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('files.delete')) {
            abort(403);
        }

        $items = DeletedItem::all();
        $count = $items->count();

        foreach ($items as $item) {
            if ($item->item_type === 'file') {
                $file = File::withTrashed()->find($item->item_id);
                if ($file) {
                    $this->storageService->deletePhysicalFile($file->storage_path, $file->storage_disk);
                    $file->forceDelete();
                }
            } elseif ($item->item_type === 'folder') {
                $folder = Folder::withTrashed()->find($item->item_id);
                if ($folder) {
                    $folder->forceDelete();
                }
            }
            $item->delete();
        }

        AuditLogService::log('recycle_bin.emptied', 'RecycleBin', null, ['purged_count' => $count]);

        return back()->with('success', "Recycle bin emptied. {$count} item(s) permanently removed.");
    }
}
