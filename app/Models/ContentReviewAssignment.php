<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReviewAssignment extends Model
{
    use HasUuids;

    public const STATUSES = ['ASSIGNED', 'SUBMITTED', 'APPROVED', 'CHANGES_REQUESTED'];

    protected $primaryKey = 'content_review_assignment_id';

    protected $fillable = [
        'framework_version_id', 'claim_type', 'assigned_by', 'reviewer_id', 'status',
        'recommendation', 'evidence_summary', 'reviewer_notes', 'submitted_at',
        'decided_by', 'decision_notes', 'decided_at',
    ];

    protected $casts = ['submitted_at' => 'datetime', 'decided_at' => 'datetime'];

    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(DepartmentFrameworkVersion::class, 'framework_version_id', 'framework_version_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'user_id');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by', 'user_id');
    }
}
