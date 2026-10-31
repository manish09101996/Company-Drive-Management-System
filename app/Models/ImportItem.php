<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_job_id',
        'entry_index',
        'entry_path',
        'entry_name',
        'entry_type',
        'size_bytes',
        'crc32',
        'status',
        'destination_folder_id',
        'folder_id',
        'file_id',
        'error_message',
        'retries',
        'checksum_sha256',
    ];

    protected $casts = [
        'entry_index' => 'integer',
        'size_bytes' => 'integer',
        'retries' => 'integer',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }

    public function destinationFolder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'destination_folder_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }
}
