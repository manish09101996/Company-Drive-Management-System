<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Folder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'parent_id',
        'department_id',
        'created_by',
        'assigned_user_id',
        'color',
        'is_favorite',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($folder) {
            if (empty($folder->uuid)) {
                $folder->uuid = (string) Str::uuid();
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Get effective assigned user ID either directly or inherited from ancestor folders.
     */
    public function getEffectiveAssignedUserId(): ?int
    {
        if ($this->assigned_user_id !== null) {
            return (int) $this->assigned_user_id;
        }

        $parent = $this->parent;
        while ($parent) {
            if ($parent->assigned_user_id !== null) {
                return (int) $parent->assigned_user_id;
            }
            $parent = $parent->parent;
        }

        return null;
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(FolderUserPermission::class);
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'uuid' => $current->uuid,
                'name' => $current->name,
            ]);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    public function getPathString(): string
    {
        $crumbs = $this->getBreadcrumbs();
        return implode(' / ', array_column($crumbs, 'name'));
    }

    public function calculateTotalSize(): int
    {
        $size = $this->files()->sum('size_bytes');
        foreach ($this->children as $child) {
            $size += $child->calculateTotalSize();
        }
        return (int) $size;
    }
}
