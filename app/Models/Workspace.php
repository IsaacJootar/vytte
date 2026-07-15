<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'workspace_id';

    protected $fillable = [
        'name',
        'workspace_type',
        'slug',
        'plan',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'workspace_id', 'workspace_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'workspace_id', 'workspace_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class, 'owner_workspace_id', 'workspace_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function ownerMember(): HasMany
    {
        return $this->members()->where('role', 'OWNER');
    }
}
