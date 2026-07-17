<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PublicResponseSession extends Model
{
    use HasUuids;

    protected $primaryKey = 'session_id';

    public $timestamps = false;

    protected $fillable = [
        'token',
        'assessment_id',
        'locale',
        'started_at',
        'last_activity_at',
        'submitted_at',
        'eligibility_status',
        'eligibility_reason',
        'is_test',
        'eligibility_reviewed_by',
        'eligibility_reviewed_at',
        'response_snapshot',
        'response_snapshot_hash',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'submitted_at' => 'datetime',
            'is_test' => 'boolean',
            'eligibility_reviewed_at' => 'datetime',
            'response_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $session): void {
            if ($session->getOriginal('submitted_at') !== null
                && ($session->isDirty('response_snapshot') || $session->isDirty('response_snapshot_hash'))
            ) {
                throw new \LogicException('Submitted respondent response snapshots are immutable.');
            }
            if ($session->submitted_at !== null
                && $session->isDirty(['eligibility_status', 'eligibility_reason', 'is_test'])
                && $session->assessment()->where('status', Assessment::STATUS_COMPLETE)->exists()
            ) {
                throw new \LogicException('Eligibility cannot change after the respondent collection is finalized.');
            }
        });
        static::deleting(function (self $session): void {
            if ($session->submitted_at !== null) {
                throw new \LogicException('Submitted respondent sessions are durable and cannot be deleted independently.');
            }
        });
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(AssessmentRespondentToken::class, 'token', 'token');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class, 'public_response_session_id', 'session_id');
    }

    public function scoreResult(): HasOne
    {
        return $this->hasOne(RespondentScoreResult::class, 'public_response_session_id', 'session_id');
    }

    public function eligibilityReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eligibility_reviewed_by', 'user_id');
    }
}
