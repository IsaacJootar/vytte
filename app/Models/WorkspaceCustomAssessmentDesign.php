<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceCustomAssessmentDesign extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $primaryKey = 'custom_assessment_design_id';

    protected $fillable = [
        'workspace_id',
        'title',
        'purpose',
        'scope',
        'setting',
        'target_population',
        'respondent_type',
        'status',
        'sections',
        'indicators',
        'questions',
        'evidence_requests',
        'descriptive_outputs',
        'private_scoring_config',
        'ai_drafting_context',
        'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'indicators' => 'array',
        'questions' => 'array',
        'evidence_requests' => 'array',
        'descriptive_outputs' => 'array',
        'private_scoring_config' => 'array',
        'ai_drafting_context' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $design): void {
            if (! in_array($design->status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
                throw new \LogicException("Unsupported workspace custom assessment status: {$design->status}.");
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'workspace_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
