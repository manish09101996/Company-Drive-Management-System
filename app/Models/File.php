<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'folder_id',
        'department_id',
        'storage_disk',
        'storage_path',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'uploaded_by',
        'is_favorite',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'is_favorite' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($file) {
            if (empty($file->uuid)) {
                $file->uuid = (string) Str::uuid();
            }
        });
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class)->orderBy('version_number', 'desc');
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(FileUserPermission::class);
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

    public function getIconClassAttribute(): string
    {
        $ext = strtolower($this->extension ?? '');
        $mime = strtolower($this->mime_type ?? '');

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp']) || str_starts_with($mime, 'image/')) {
            return 'bi-file-earmark-image text-primary';
        }
        if ($ext === 'pdf' || $mime === 'application/pdf') {
            return 'bi-file-earmark-pdf text-danger';
        }
        if (in_array($ext, ['doc', 'docx', 'odt', 'rtf'])) {
            return 'bi-file-earmark-word text-info';
        }
        if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods'])) {
            return 'bi-file-earmark-excel text-success';
        }
        if (in_array($ext, ['ppt', 'pptx', 'odp'])) {
            return 'bi-file-earmark-slides text-warning';
        }
        if (in_array($ext, ['zip', 'rar', 'tar', 'gz', '7z'])) {
            return 'bi-file-earmark-zip text-secondary';
        }
        if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm']) || str_starts_with($mime, 'video/')) {
            return 'bi-file-earmark-play text-danger';
        }
        if (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'flac']) || str_starts_with($mime, 'audio/')) {
            return 'bi-file-earmark-music text-purple';
        }
        if (in_array($ext, ['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'sql', 'py'])) {
            return 'bi-file-earmark-code text-dark';
        }

        return 'bi-file-earmark text-muted';
    }

    public function isPreviewable(): bool
    {
        $mime = strtolower($this->mime_type ?? '');
        $ext = strtolower($this->extension ?? '');

        return str_starts_with($mime, 'image/')
            || $mime === 'application/pdf'
            || str_starts_with($mime, 'text/')
            || in_array($ext, ['txt', 'md', 'json', 'log', 'csv'])
            || str_starts_with($mime, 'video/')
            || str_starts_with($mime, 'audio/');
    }
}
