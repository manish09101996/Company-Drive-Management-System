<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateStorageService
{
    protected string $disk = 'private';

    /**
     * Store an uploaded file privately and return file metadata.
     */
    public function storeUploadedFile(UploadedFile $file, ?string $subDir = 'files'): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';
        $sizeBytes = $file->getSize();

        // Calculate SHA-256 before moving
        $checksum = hash_file('sha256', $file->getRealPath());

        // Generate non-guessable private storage path
        $yearMonth = date('Y/m');
        $randomFileName = Str::random(40) . ($extension ? '.' . $extension : '.bin');
        $storagePath = "{$subDir}/{$yearMonth}/{$randomFileName}";

        // Store into private disk
        $stream = fopen($file->getRealPath(), 'r');
        Storage::disk($this->disk)->put($storagePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return [
            'name' => $originalName,
            'storage_disk' => $this->disk,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
        ];
    }

    /**
     * Store raw stream/content into private disk.
     */
    public function storeRawStream($stream, string $originalName, ?string $mimeType = null, ?string $subDir = 'files'): array
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $yearMonth = date('Y/m');
        $randomFileName = Str::random(40) . ($extension ? '.' . $extension : '.bin');
        $storagePath = "{$subDir}/{$yearMonth}/{$randomFileName}";

        Storage::disk($this->disk)->put($storagePath, $stream);

        $fullPath = Storage::disk($this->disk)->path($storagePath);
        $sizeBytes = file_exists($fullPath) ? filesize($fullPath) : 0;
        $checksum = file_exists($fullPath) ? hash_file('sha256', $fullPath) : null;

        return [
            'name' => $originalName,
            'storage_disk' => $this->disk,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType ?: 'application/octet-stream',
            'extension' => strtolower($extension),
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
        ];
    }

    /**
     * Create a secure streamed response for download or inline preview.
     * Supports HTTP Range requests for video/audio seek and fast downloads.
     */
    public function streamFile(File $file, bool $inline = false): StreamedResponse
    {
        $disk = Storage::disk($file->storage_disk ?: $this->disk);
        $path = $file->storage_path;

        if (!$disk->exists($path)) {
            abort(404, 'The requested file could not be found in storage.');
        }

        $fullPath = $disk->path($path);
        $fileSize = $file->size_bytes > 0 ? $file->size_bytes : filesize($fullPath);
        $mimeType = $file->mime_type ?: 'application/octet-stream';
        $dispositionType = $inline ? 'inline' : 'attachment';
        $safeFilename = str_replace(['"', "\r", "\n"], '', $file->name);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "{$dispositionType}; filename=\"{$safeFilename}\"",
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ];

        // Handle HTTP Range header for streaming
        $range = request()->server('HTTP_RANGE');
        if ($range && preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches)) {
            $start = intval($matches[1]);
            $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;

            if ($start > $end || $start >= $fileSize) {
                return response()->stream(function () {}, 416, [
                    'Content-Range' => "bytes */{$fileSize}",
                ]);
            }

            $length = $end - $start + 1;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
            $headers['Content-Length'] = $length;

            return response()->stream(function () use ($fullPath, $start, $length) {
                $handle = fopen($fullPath, 'rb');
                fseek($handle, $start);
                $remaining = $length;
                $chunkSize = 1024 * 64; // 64KB buffer

                while (!feof($handle) && $remaining > 0) {
                    $readSize = min($chunkSize, $remaining);
                    echo fread($handle, $readSize);
                    flush();
                    $remaining -= $readSize;
                }
                fclose($handle);
            }, 206, $headers);
        }

        $headers['Content-Length'] = $fileSize;

        return response()->stream(function () use ($fullPath) {
            $handle = fopen($fullPath, 'rb');
            $chunkSize = 1024 * 64;
            while (!feof($handle)) {
                echo fread($handle, $chunkSize);
                flush();
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Delete a physical file from storage.
     */
    public function deletePhysicalFile(string $storagePath, ?string $storageDisk = null): bool
    {
        $disk = Storage::disk($storageDisk ?: $this->disk);
        if ($disk->exists($storagePath)) {
            return $disk->delete($storagePath);
        }
        return false;
    }

    /**
     * Get configured company drive storage quota in bytes.
     */
    public function getStorageQuotaBytes(): int
    {
        $quotaGb = (float) config('company_drive.storage_quota_gb', env('STORAGE_QUOTA_GB', 50));
        return (int) round($quotaGb * 1024 * 1024 * 1024);
    }

    /**
     * Get disk storage stats and company quota metrics.
     */
    public function getStorageStats(): array
    {
        $storageDir = storage_path('app/private');
        if (!file_exists($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }

        $quotaBytes = $this->getStorageQuotaBytes();
        $usedBytes = (int) File::sum('size_bytes');
        $freeBytes = max(0, $quotaBytes - $usedBytes);
        $usedPercentage = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 1) : 0;

        $diskFree = @disk_free_space($storageDir) ?: 0;
        $diskTotal = @disk_total_space($storageDir) ?: 0;

        $inodesLimit = (int) config('company_drive.inodes_limit', 600000);
        $inodesUsed = (int) (\App\Models\File::count() + \App\Models\Folder::count());
        $inodesPercentage = $inodesLimit > 0 ? round(($inodesUsed / $inodesLimit) * 100, 2) : 0;

        return [
            'quota_bytes' => $quotaBytes,
            'quota_formatted' => $this->formatBytes($quotaBytes),
            'free_bytes' => $freeBytes,
            'total_bytes' => $quotaBytes,
            'used_bytes' => $usedBytes,
            'free_formatted' => $this->formatBytes($freeBytes),
            'total_formatted' => $this->formatBytes($quotaBytes),
            'used_formatted' => $this->formatBytes($usedBytes),
            'used_percentage' => $usedPercentage,
            'plan_name' => config('company_drive.plan_name', 'Hostinger Unlimited Web Hosting (50 GB NVMe)'),
            'inodes_limit' => $inodesLimit,
            'inodes_used' => $inodesUsed,
            'inodes_formatted' => number_format($inodesUsed),
            'inodes_limit_formatted' => number_format($inodesLimit),
            'inodes_percentage' => $inodesPercentage,
            'php_workers' => (int) config('company_drive.php_workers', 60),
            'mysql_connections' => (int) config('company_drive.mysql_connections', 75),
            'physical_disk_free' => $diskFree,
            'physical_disk_free_formatted' => $this->formatBytes($diskFree),
            'physical_disk_total' => $diskTotal,
            'physical_disk_total_formatted' => $this->formatBytes($diskTotal),
        ];
    }

    public function formatBytes(int|float $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }
}
