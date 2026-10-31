<?php

namespace App\Console\Commands;

use App\Models\DeletedItem;
use App\Models\File;
use App\Models\Folder;
use App\Services\AuditLogService;
use App\Services\PrivateStorageService;
use Illuminate\Console\Command;

class PurgeRecycleBinCommand extends Command
{
    protected $signature = 'drive:purge-recycle-bin {--days=30 : Purge items deleted more than this many days ago}';
    protected $description = 'Permanently purge expired items from the recycle bin and remove physical files';

    public function handle(PrivateStorageService $storageService): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $expired = DeletedItem::where('deleted_at', '<=', $cutoffDate)
            ->orWhere(function ($q) {
                $q->whereNotNull('expires_at')->where('expires_at', '<=', now());
            })
            ->get();

        if ($expired->isEmpty()) {
            $this->info("No expired items found in recycle bin.");
            return 0;
        }

        $purgedCount = 0;
        foreach ($expired as $item) {
            if ($item->item_type === 'file') {
                $file = File::withTrashed()->find($item->item_id);
                if ($file) {
                    $storageService->deletePhysicalFile($file->storage_path, $file->storage_disk);
                    $file->forceDelete();
                }
            } elseif ($item->item_type === 'folder') {
                $folder = Folder::withTrashed()->find($item->item_id);
                if ($folder) {
                    $folder->forceDelete();
                }
            }

            $item->delete();
            $purgedCount++;
        }

        AuditLogService::log('recycle_bin.auto_purged', 'RecycleBin', null, [
            'purged_count' => $purgedCount,
            'retention_days' => $days,
        ]);

        $this->info("Purged {$purgedCount} expired items from recycle bin.");
        return 0;
    }
}
