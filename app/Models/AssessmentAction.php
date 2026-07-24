<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A living action drawn from a frozen recommendation.
 *
 * Unlike everything else in a report, an action changes: it is owned, scheduled, worked,
 * and eventually verified. Its provenance (the finding and recommendation it came from) is
 * copied in at creation and never edited, so the action can be trusted to trace back to
 * real evidence no matter how its living fields move.
 */
class AssessmentAction extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected $primaryKey = 'action_id';

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_DONE = 'DONE';

    public const STATUS_VERIFIED = 'VERIFIED';

    /** The ordered lifecycle. Every status a valid action can hold. */
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_VERIFIED];

    public const PRIORITIES = ['HIGH', 'MEDIUM', 'LOW'];

    protected $fillable = [
        'workspace_id', 'assessment_id', 'project_id',
        'source_finding_category', 'source_finding_subject', 'source_finding_statement',
        'source_measurement_domain', 'recommendation_statement',
        'title', 'owner_user_id', 'priority', 'due_date', 'status',
        'verified_by', 'verified_at', 'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ActionUpdate::class, 'action_id', 'action_id')->latest('created_at');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    /** A closed action is one the org considers finished — done or verified. */
    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_VERIFIED], true);
    }

    /** Past its due date and not yet finished. */
    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast() && ! $this->isClosed();
    }
}
