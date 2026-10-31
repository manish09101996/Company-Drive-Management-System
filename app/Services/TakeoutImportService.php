<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use App\Models\ImportItem;
use App\Models\ImportJob;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class TakeoutImportService
{
    protected string $disk = 'private';
    protected PrivateStorageService $storageService;

    public function __construct(PrivateStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Store an uploaded chunk and merge when complete.
     */
    public function handleChunkUpload(
        UploadedFile $chunk,
        string $uploadId,
        int $chunkIndex,
        int $totalChunks,
        string $originalFilename
    ): array {
        $tempDir = storage_path("app/private/imports/chunks/{$uploadId}");
        if (!file_exists($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $chunkPath = "{$tempDir}/chunk_{$chunkIndex}.part";
        $chunk->move($tempDir, "chunk_{$chunkIndex}.part");

        // Check if all chunks have been received
        $receivedChunks = glob("{$tempDir}/chunk_*.part");
        if (count($receivedChunks) === $totalChunks) {
            // Merge all chunks into final ZIP archive
            $archiveDir = storage_path("app/private/imports/archives");
            if (!file_exists($archiveDir)) {
                @mkdir($archiveDir, 0755, true);
            }

            $jobUuid = (string) Str::uuid();
            $safeExt = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'zip';
            $finalPath = "{$archiveDir}/takeout_{$jobUuid}.{$safeExt}";
            $outHandle = fopen($finalPath, 'wb');

            for ($i = 0; $i < $totalChunks; $i++) {
                $partPath = "{$tempDir}/chunk_{$i}.part";
                if (!file_exists($partPath)) {
                    fclose($outHandle);
                    throw new Exception("Missing chunk {$i} during archive reassembly.");
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

            $archiveRelativePath = "imports/archives/takeout_{$jobUuid}.{$safeExt}";
            $archiveSizeBytes = filesize($finalPath);

            return [
                'complete' => true,
                'archive_path' => $archiveRelativePath,
                'archive_filename' => $originalFilename,
                'archive_size_bytes' => $archiveSizeBytes,
                'job_uuid' => $jobUuid,
            ];
        }

        return [
            'complete' => false,
            'received' => count($receivedChunks),
            'total' => $totalChunks,
        ];
    }

    /**
     * Inspect archive for security vulnerabilities, path traversal, zip bombs, and generate manifest.
     */
    public function inspectArchive(ImportJob $job): array
    {
        $fullPath = storage_path("app/private/{$job->archive_path}");
        if (!file_exists($fullPath)) {
            throw new Exception("Archive file not found at: {$fullPath}");
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($fullPath);
        if ($openResult !== true) {
            $job->update([
                'status' => 'failed',
                'error_summary' => "Cannot open ZIP archive. ZipArchive error code: {$openResult}",
            ]);
            throw new Exception("Cannot open ZIP archive (code: {$openResult})");
        }

        $totalEntries = $zip->numFiles;
        $totalUncompressedSize = 0;
        $totalCompressedSize = 0;
        $foldersCount = 0;
        $filesCount = 0;

        $suspiciousEntries = [];
        $itemsToInsert = [];

        // Check entries
        for ($i = 0; $i < $totalEntries; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $rawName = $stat['name'];
            $entrySize = $stat['size'];
            $compressedSize = $stat['comp_size'];

            $totalUncompressedSize += $entrySize;
            $totalCompressedSize += $compressedSize;

            // 1. Check for Zip Slip / Path Traversal
            if (
                str_contains($rawName, '../') ||
                str_contains($rawName, '..\\') ||
                str_starts_with($rawName, '/') ||
                str_starts_with($rawName, '\\') ||
                preg_match('/^[a-zA-Z]:/', $rawName)
            ) {
                $suspiciousEntries[] = "Path traversal entry detected: {$rawName}";
                continue;
            }

            // Skip common metadata junk
            if (str_contains($rawName, '__MACOSX') || str_ends_with($rawName, '.DS_Store') || str_ends_with($rawName, 'Thumbs.db')) {
                continue;
            }

            $isDir = str_ends_with($rawName, '/') || str_ends_with($rawName, '\\');
            $cleanPath = trim(str_replace('\\', '/', $rawName), '/');
            if (empty($cleanPath)) {
                continue;
            }

            $entryName = basename($cleanPath);
            $entryType = $isDir ? 'folder' : 'file';

            if ($isDir) {
                $foldersCount++;
            } else {
                $filesCount++;
            }

            $itemsToInsert[] = [
                'import_job_id' => $job->id,
                'entry_index' => $i,
                'entry_path' => $cleanPath,
                'entry_name' => $entryName,
                'entry_type' => $entryType,
                'size_bytes' => $entrySize,
                'crc32' => dechex($stat['crc'] ?? 0),
                'status' => 'pending',
                'retries' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        $zip->close();

        // 2. Check for Zip Bomb (compression ratio > 100:1)
        if ($totalCompressedSize > 0 && ($totalUncompressedSize / $totalCompressedSize) > 100) {
            $job->update([
                'status' => 'failed',
                'error_summary' => 'Suspicious compression ratio detected (>100:1). Archive rejected for potential zip bomb vulnerability.',
            ]);
            throw new Exception('Potential ZIP bomb detected. Archive rejected.');
        }

        // 3. Check Server Available Storage Space
        $storageStats = $this->storageService->getStorageStats();
        $requiredBytes = $totalUncompressedSize * 1.2; // 20% safety margin
        $storageWarning = null;
        if ($requiredBytes > $storageStats['free_bytes']) {
            $storageWarning = "Warning: Required uncompressed space (" . $this->storageService->formatBytes($totalUncompressedSize) . ") exceeds safe disk space available (" . $storageStats['free_formatted'] . ").";
        }

        // Save items in chunks to database
        ImportItem::where('import_job_id', $job->id)->delete();
        foreach (array_chunk($itemsToInsert, 250) as $chunk) {
            ImportItem::insert($chunk);
        }

        $settings = $job->settings ?? [];
        $settings['total_uncompressed_bytes'] = $totalUncompressedSize;

        $job->update([
            'status' => 'manifest_ready',
            'total_entries' => count($itemsToInsert),
            'total_files' => $filesCount,
            'total_folders' => $foldersCount,
            'settings' => $settings,
            'error_summary' => $storageWarning ? $storageWarning : (count($suspiciousEntries) > 0 ? implode("; ", array_slice($suspiciousEntries, 0, 5)) : null),
        ]);

        return [
            'total_entries' => count($itemsToInsert),
            'total_files' => $filesCount,
            'total_folders' => $foldersCount,
            'total_uncompressed_bytes' => $totalUncompressedSize,
            'total_uncompressed_formatted' => $this->storageService->formatBytes($totalUncompressedSize),
            'storage_warning' => $storageWarning,
            'suspicious_entries' => $suspiciousEntries,
        ];
    }

    /**
     * Process one controlled batch of import items (designed for shared hosting execution limits).
     * Returns batch summary with processed counts, errors, and remaining count.
     */
    public function processBatch(ImportJob $job, int $batchSize = 50, ?User $currentUser = null): array
    {
        if (in_array($job->status, ['completed', 'cancelled'])) {
            return [
                'status' => $job->status,
                'processed' => 0,
                'remaining' => 0,
                'message' => "Job is already {$job->status}.",
            ];
        }

        if ($job->status !== 'processing') {
            $job->update([
                'status' => 'processing',
                'started_at' => $job->started_at ?: now(),
            ]);
        }

        $fullPath = storage_path("app/private/{$job->archive_path}");
        if (!file_exists($fullPath)) {
            $job->update(['status' => 'failed', 'error_summary' => 'Archive file not found on disk.']);
            throw new Exception("Archive file not found on disk.");
        }

        // Fetch next pending items (or failed items if user initiated retry)
        $items = ImportItem::where('import_job_id', $job->id)
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('entry_type', 'asc') // Create folders before files
            ->orderBy('id', 'asc')
            ->limit($batchSize)
            ->get();

        if ($items->isEmpty()) {
            // Check if any items are still failed
            $failedCount = ImportItem::where('import_job_id', $job->id)->where('status', 'failed')->count();
            $newStatus = $failedCount > 0 ? 'partially_completed' : 'completed';

            $job->update([
                'status' => $newStatus,
                'completed_at' => now(),
            ]);

            return [
                'status' => $newStatus,
                'processed' => 0,
                'remaining' => 0,
                'message' => "Import job completed.",
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($fullPath) !== true) {
            throw new Exception("Could not open archive during batch processing.");
        }

        $importedInBatch = 0;
        $skippedInBatch = 0;
        $failedInBatch = 0;
        $folderCache = [];

        $settings = $job->settings ?? [];
        $duplicateAction = $settings['duplicate_action'] ?? 'skip';

        foreach ($items as $item) {
            try {
                if ($item->entry_type === 'folder') {
                    $targetFolder = $this->resolveOrCreateFolderPath(
                        $job,
                        $item->entry_path,
                        $folderCache,
                        $currentUser
                    );
                    $item->update([
                        'status' => 'completed',
                        'folder_id' => $targetFolder->id,
                        'destination_folder_id' => $targetFolder->parent_id,
                        'error_message' => null,
                    ]);
                    $importedInBatch++;
                } else {
                    // Extract file stream
                    $dirName = dirname($item->entry_path);
                    $parentFolder = null;
                    if ($dirName !== '.' && !empty($dirName)) {
                        $parentFolder = $this->resolveOrCreateFolderPath(
                            $job,
                            $dirName,
                            $folderCache,
                            $currentUser
                        );
                    } else {
                        $parentFolder = $job->destinationFolder;
                    }

                    // Duplicate file detection
                    $targetName = $item->entry_name;
                    $existingFile = File::where('folder_id', $parentFolder?->id)
                        ->where('name', $targetName)
                        ->whereNull('deleted_at')
                        ->first();

                    if ($existingFile) {
                        if ($duplicateAction === 'skip') {
                            $item->update([
                                'status' => 'skipped',
                                'destination_folder_id' => $parentFolder?->id,
                                'error_message' => 'Skipped: Duplicate file with identical name already exists in destination.',
                            ]);
                            $skippedInBatch++;
                            continue;
                        } elseif ($duplicateAction === 'rename') {
                            $pathInfo = pathinfo($targetName);
                            $counter = 1;
                            $baseNameOnly = $pathInfo['filename'] ?? 'file';
                            $extOnly = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                            do {
                                $targetName = "{$baseNameOnly} ({$counter}){$extOnly}";
                                $counter++;
                            } while (File::where('folder_id', $parentFolder?->id)->where('name', $targetName)->whereNull('deleted_at')->exists());
                        }
                    }

                    $stream = $zip->getStream($zip->getNameIndex($item->entry_index));
                    if (!$stream) {
                        throw new Exception("Could not read entry stream at index {$item->entry_index}");
                    }

                    // Save stream to private storage
                    $stored = $this->storageService->storeRawStream(
                        $stream,
                        $targetName,
                        null,
                        'files'
                    );
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    if ($existingFile && $duplicateAction === 'overwrite') {
                        // Store previous version in file_versions
                        $latestVer = \App\Models\FileVersion::where('file_id', $existingFile->id)->max('version_number') ?: 1;
                        \App\Models\FileVersion::create([
                            'file_id' => $existingFile->id,
                            'version_number' => $latestVer + 1,
                            'storage_disk' => $stored['storage_disk'],
                            'storage_path' => $stored['storage_path'],
                            'size_bytes' => $stored['size_bytes'],
                            'checksum_sha256' => $stored['checksum_sha256'],
                            'uploaded_by' => $currentUser?->id ?? $job->created_by,
                            'change_summary' => 'Overwritten by archive extraction (' . $job->archive_filename . ')',
                        ]);

                        $existingFile->update([
                            'storage_disk' => $stored['storage_disk'],
                            'storage_path' => $stored['storage_path'],
                            'mime_type' => $stored['mime_type'],
                            'extension' => $stored['extension'],
                            'size_bytes' => $stored['size_bytes'],
                            'checksum_sha256' => $stored['checksum_sha256'],
                        ]);

                        $item->update([
                            'status' => 'completed',
                            'file_id' => $existingFile->id,
                            'destination_folder_id' => $parentFolder?->id,
                            'checksum_sha256' => $stored['checksum_sha256'],
                            'error_message' => 'Overwrote existing file with version ' . ($latestVer + 1),
                        ]);
                    } else {
                        // Create File record
                        $createdFile = File::create([
                            'uuid' => (string) Str::uuid(),
                            'name' => $targetName,
                            'folder_id' => $parentFolder?->id,
                            'department_id' => $parentFolder?->department_id,
                            'storage_disk' => $stored['storage_disk'],
                            'storage_path' => $stored['storage_path'],
                            'mime_type' => $stored['mime_type'],
                            'extension' => $stored['extension'],
                            'size_bytes' => $stored['size_bytes'],
                            'checksum_sha256' => $stored['checksum_sha256'],
                            'uploaded_by' => $currentUser?->id ?? $job->created_by,
                        ]);

                        $item->update([
                            'status' => 'completed',
                            'file_id' => $createdFile->id,
                            'destination_folder_id' => $parentFolder?->id,
                            'checksum_sha256' => $stored['checksum_sha256'],
                            'error_message' => null,
                        ]);
                    }

                    $importedInBatch++;
                }
            } catch (Exception $e) {
                $item->update([
                    'status' => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 500),
                    'retries' => $item->retries + 1,
                ]);
                $failedInBatch++;
            }
        }
        $zip->close();

        // Update Job counters
        $completedCount = ImportItem::where('import_job_id', $job->id)->where('status', 'completed')->count();
        $failedCount = ImportItem::where('import_job_id', $job->id)->where('status', 'failed')->count();
        $skippedCount = ImportItem::where('import_job_id', $job->id)->where('status', 'skipped')->count();
        $remainingCount = ImportItem::where('import_job_id', $job->id)->whereIn('status', ['pending'])->count();

        $job->update([
            'imported_entries' => $completedCount,
            'failed_entries' => $failedCount,
            'skipped_entries' => $skippedCount,
        ]);

        if ($remainingCount === 0) {
            $finalStatus = $failedCount > 0 ? 'partially_completed' : 'completed';
            $job->update([
                'status' => $finalStatus,
                'completed_at' => now(),
            ]);

            // Check if user requested deleting the source ZIP after successful verification
            if ($finalStatus === 'completed' && !empty($job->settings['delete_source_zip'])) {
                $this->cleanupSourceArchive($job);
            }
        }

        return [
            'status' => $job->fresh()->status,
            'imported_in_batch' => $importedInBatch,
            'skipped_in_batch' => $skippedInBatch,
            'failed_in_batch' => $failedInBatch,
            'total_imported' => $completedCount,
            'total_skipped' => $skippedCount,
            'total_failed' => $failedCount,
            'remaining' => $remainingCount,
            'progress_percent' => $job->progress_percentage,
            'destination_folder_uuid' => $job->destinationFolder?->uuid,
        ];
    }

    /**
     * Clean up / delete source archive after successful extraction and verification.
     */
    public function cleanupSourceArchive(ImportJob $job): void
    {
        $settings = $job->settings ?? [];

        // If it was linked to an existing File record in Company Drive
        if (!empty($settings['source_file_id'])) {
            $sourceFile = File::find($settings['source_file_id']);
            if ($sourceFile) {
                $sourceFile->delete(); // Soft delete or delete
                AuditLogService::log('file.deleted_after_extraction', 'File', $sourceFile->id, [
                    'archive_filename' => $sourceFile->name,
                    'import_job_uuid' => $job->uuid,
                ]);
            }
        }

        // Delete physical archive file from disk if still present
        $fullPath = storage_path("app/private/{$job->archive_path}");
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        $settings['source_deleted'] = true;
        $job->update(['settings' => $settings]);

        AuditLogService::log('import.source_archive_cleaned', 'ImportJob', $job->id, [
            'archive_filename' => $job->archive_filename,
        ]);
    }

    /**
     * Resolves nested folder path and creates missing database folders along the path.
     */
    protected function resolveOrCreateFolderPath(
        ImportJob $job,
        string $relativePath,
        array &$cache,
        ?User $currentUser = null
    ): Folder {
        $cleanPath = trim(str_replace('\\', '/', $relativePath), '/');

        // Optional: Strip common Takeout root folder wrapper e.g. "Takeout/Drive"
        $parts = explode('/', $cleanPath);
        if (count($parts) >= 2 && strtolower($parts[0]) === 'takeout') {
            array_shift($parts);
            if (isset($parts[0]) && strtolower($parts[0]) === 'drive') {
                array_shift($parts);
            }
        }

        $currentParentId = $job->destination_folder_id;
        $cumulativePath = '';
        $lastFolder = null;

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }
            $cumulativePath .= ($cumulativePath === '' ? '' : '/') . $part;

            if (isset($cache[$cumulativePath])) {
                $lastFolder = $cache[$cumulativePath];
                $currentParentId = $lastFolder->id;
                continue;
            }

            // Determine assigned user from parent hierarchy or ownership mapping
            $parentFolderModel = $currentParentId ? Folder::find($currentParentId) : null;
            $assignedUserId = $parentFolderModel ? $parentFolderModel->getEffectiveAssignedUserId() : null;

            if ($assignedUserId === null) {
                $assignedUserId = $this->resolveAssignedUserForFolder($part, $parentFolderModel);
            }

            // Find or create in DB
            $folder = Folder::firstOrCreate(
                [
                    'name' => $part,
                    'parent_id' => $currentParentId,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'department_id' => $job->destinationFolder?->department_id,
                    'created_by' => $currentUser?->id ?? $job->created_by,
                    'assigned_user_id' => $assignedUserId,
                    'color' => '#4f46e5',
                ]
            );

            $cache[$cumulativePath] = $folder;
            $lastFolder = $folder;
            $currentParentId = $folder->id;
        }

        $adminFallback = Folder::where('name', 'Admin Folder')->whereNull('parent_id')->first();

        return $lastFolder ?: ($job->destinationFolder ?? $adminFallback ?? Folder::firstOrCreate(
            ['name' => 'Imported Files', 'parent_id' => null],
            ['uuid' => (string) Str::uuid(), 'created_by' => $currentUser?->id ?? $job->created_by]
        ));
    }

    /**
     * Ownership keyword mapping for company employees.
     */
    public static function getOwnershipMapping(): array
    {
        return [
            'garima@arklehomes.com.au' => ['garima'],
            'ishan@arklehomes.com.au'  => ['ishan'],
            'sean@arklehomes.com.au'   => ['sean'],
            'skmann@arklehomes.com.au' => ['sk mann', 'skmann'],
        ];
    }

    /**
     * Resolve assigned user ID for an extracted folder.
     */
    public function resolveAssignedUserForFolder(string $folderName, ?Folder $parent = null): ?int
    {
        if ($parent && $parent->getEffectiveAssignedUserId() !== null) {
            return $parent->getEffectiveAssignedUserId();
        }

        $lowerName = strtolower($folderName);
        foreach (self::getOwnershipMapping() as $email => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lowerName, $kw)) {
                    $user = User::where('email', $email)->first();
                    if ($user) {
                        return $user->id;
                    }
                }
            }
        }

        return null;
    }
}
