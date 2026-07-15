<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Target extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected $primaryKey = 'target_id';

    protected $fillable = [
        'owner_workspace_id',
        'project_id',
        'target_type_code',
        'name',
        'category_id',
        'state',
        'lga',
        'ownership',
        'latitude',
        'longitude',
    ];

    public function getWorkspaceForeignKey(): string
    {
        return 'owner_workspace_id';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'owner_workspace_id', 'workspace_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TargetCategory::class, 'category_id', 'category_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'target_id', 'target_id');
    }
}
