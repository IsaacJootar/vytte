<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected $primaryKey = 'project_id';

    protected $fillable = [
        'workspace_id',
        'owner_user_id',
        'name',
        'description',
        'topic_id',
        'status',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'workspace_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'user_id');
    }

    public function targets(): BelongsToMany
    {
        return $this->belongsToMany(Target::class, 'project_targets', 'project_id', 'target_id')
            ->withPivot('added_at');
    }

    public function primaryTarget(): ?Target
    {
        return $this->targets->first();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'project_id', 'project_id');
    }

    public function getRouteKeyName(): string
    {
        return 'project_id';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isArchived(): bool
    {
        return $this->status === 'ARCHIVED';
    }
}
