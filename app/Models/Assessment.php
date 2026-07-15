<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'assessment_id';

    protected $fillable = [
        'target_id',
        'project_id',
        'assessment_tier_id',
        'scope_type',
        'status',
        'publish_status',
        'published_at',
        'published_by',
        'started_at',
        'completed_at',
        'assessor_name',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class, 'target_id', 'target_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(AssessmentTier::class, 'assessment_tier_id', 'assessment_tier_id');
    }

    public function moduleScope(): HasMany
    {
        return $this->hasMany(AssessmentModuleScope::class, 'assessment_id', 'assessment_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class, 'assessment_id', 'assessment_id');
    }

    public function score(): HasOne
    {
        return $this->hasOne(AssessmentScore::class, 'assessment_id', 'assessment_id');
    }

    public function isDraft(): bool
    {
        return $this->publish_status === 'DRAFT';
    }

    public function isPublished(): bool
    {
        return $this->publish_status === 'PUBLISHED';
    }

    public function isComplete(): bool
    {
        return $this->status === 'COMPLETE';
    }
}
