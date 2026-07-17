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

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETE = 'COMPLETE';

    public const PUBLISH_DRAFT = 'DRAFT';

    public const PUBLISH_PUBLISHED = 'PUBLISHED';

    protected $primaryKey = 'assessment_id';

    protected $attributes = [
        'status' => self::STATUS_IN_PROGRESS,
        'publish_status' => self::PUBLISH_DRAFT,
    ];

    protected $fillable = [
        'target_id',
        'project_id',
        'assessment_tier_id',
        'scope_type',
        'creation_path',
        'template_version_id',
        'composition_hash',
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

    protected static function booted(): void
    {
        static::saving(function (self $assessment): void {
            if (! in_array($assessment->status, [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETE], true)) {
                throw new \LogicException("Unsupported assessment status: {$assessment->status}.");
            }

            if (! in_array($assessment->publish_status, [self::PUBLISH_DRAFT, self::PUBLISH_PUBLISHED], true)) {
                throw new \LogicException("Unsupported assessment publication status: {$assessment->publish_status}.");
            }

            if ($assessment->exists && $assessment->isDirty('status')) {
                $from = $assessment->getOriginal('status');
                $validCompletion = $from === self::STATUS_IN_PROGRESS
                    && $assessment->status === self::STATUS_COMPLETE;

                if (! $validCompletion) {
                    throw new \LogicException("Assessment transition {$from} -> {$assessment->status} is not allowed.");
                }
            }

            if ($assessment->status === self::STATUS_COMPLETE && $assessment->completed_at === null) {
                throw new \LogicException('A completed assessment requires completed_at.');
            }
        });
    }

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

    public function snapshot(): HasOne
    {
        return $this->hasOne(AssessmentSnapshot::class, 'assessment_id', 'assessment_id');
    }

    public function reportSnapshot(): HasOne
    {
        return $this->hasOne(AssessmentReportSnapshot::class, 'assessment_id', 'assessment_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplateVersion::class, 'template_version_id', 'template_version_id');
    }

    public function getRouteKeyName(): string
    {
        return 'assessment_id';
    }

    public function isDraft(): bool
    {
        return $this->publish_status === self::PUBLISH_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->publish_status === self::PUBLISH_PUBLISHED;
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }
}
