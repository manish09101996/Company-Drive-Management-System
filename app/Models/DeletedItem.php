<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_type',
        'item_id',
        'original_parent_id',
        'original_name',
        'size_bytes',
        'deleted_by',
        'deleted_at',
        'expires_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'expires_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function originalParentFolder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'original_parent_id')->withTrashed();
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
