<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A goal score for a project — overall, or for one measurement domain.
 *
 * A target gives the trend a destination: the report can say not only "up 6 since last time"
 * but "12 short of the goal". Workspace-scoped and set by the org, never by the platform.
 */
class PerformanceTarget extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected $primaryKey = 'target_goal_id';

    protected $fillable = [
        'workspace_id', 'project_id', 'domain_code', 'target_score', 'created_by',
    ];

    protected $casts = [
        'target_score' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function isOverall(): bool
    {
        return $this->domain_code === null;
    }
}
