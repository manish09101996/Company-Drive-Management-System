<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderUserPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'folder_id',
        'user_id',
        'can_view',
        'can_upload',
        'can_download',
        'can_edit',
        'can_delete',
        'can_share',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_upload' => 'boolean',
        'can_download' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_share' => 'boolean',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
