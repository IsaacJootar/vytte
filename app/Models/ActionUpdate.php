<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in an action's history — a progress note, a status change, or evidence.
 *
 * The append-only trail behind an action. It is what a later Progress assessment reads to
 * answer "was the agreed action actually done, and can we see the proof?"
 */
class ActionUpdate extends Model
{
    use HasUuids;

    protected $primaryKey = 'action_update_id';

    public $timestamps = false;

    protected $fillable = [
        'action_id', 'workspace_id', 'author_user_id',
        'note', 'status_from', 'status_to', 'evidence_note', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(AssessmentAction::class, 'action_id', 'action_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id', 'user_id');
    }

    public function isStatusChange(): bool
    {
        return $this->status_to !== null && $this->status_to !== $this->status_from;
    }
}
