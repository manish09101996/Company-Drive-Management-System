<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'source_type',
        'archive_filename',
        'archive_path',
        'total_parts',
        'archive_size_bytes',
        'destination_folder_id',
        'default_access_role',
        'status',
        'total_entries',
        'total_files',
        'total_folders',
        'imported_entries',
        'failed_entries',
        'skipped_entries',
        'error_summary',
        'settings',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_parts' => 'integer',
        'archive_size_bytes' => 'integer',
        'total_entries' => 'integer',
        'total_files' => 'integer',
        'total_folders' => 'integer',
        'imported_entries' => 'integer',
        'failed_entries' => 'integer',
        'skipped_entries' => 'integer',
        'settings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function destinationFolder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'destination_folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_entries <= 0) {
            return 0.0;
        }

        $processed = $this->imported_entries + $this->failed_entries + $this->skipped_entries;
        return round(min(100, ($processed / $this->total_entries) * 100), 1);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->archive_size_bytes;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }

    public function getEstimatedExtractedSizeAttribute(): string
    {
        $bytes = $this->settings['total_uncompressed_bytes'] ?? null;
        if ($bytes === null || $bytes <= 0) {
            $bytes = $this->items()->sum('size_bytes');
        }
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }
}
