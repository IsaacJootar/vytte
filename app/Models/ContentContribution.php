<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentContribution extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const STATUSES = ['SUBMITTED', 'IN_REVIEW', 'NEEDS_CHANGES', 'ACCEPTED', 'REJECTED', 'PROMOTED'];

    protected $primaryKey = 'content_contribution_id';

    protected $fillable = [
        'workspace_id', 'submitted_by', 'proposed_publisher_id', 'module_id', 'title',
        'question_text', 'response_format', 'answer_options', 'numeric_config', 'intended_use', 'source_authority',
        'source_url', 'license_code', 'methodology_notes', 'status', 'reviewed_by',
        'review_notes', 'reviewed_at', 'promoted_question_id', 'promoted_question_version_id',
    ];

    protected $casts = [
        'answer_options' => 'array',
        'numeric_config' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $contribution): void {
            if (! in_array($contribution->status, self::STATUSES, true)) {
                throw new \LogicException("Unsupported contribution status: {$contribution->status}.");
            }
            if ($contribution->exists && $contribution->getOriginal('status') === 'PROMOTED' && $contribution->isDirty()) {
                throw new \LogicException('Promoted contributions are immutable audit records.');
            }
        });
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }

    public function proposedPublisher(): BelongsTo
    {
        return $this->belongsTo(ContentPublisher::class, 'proposed_publisher_id', 'content_publisher_id');
    }
}
