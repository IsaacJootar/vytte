<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespondentScoreResult extends Model
{
    use HasUuids;

    protected $primaryKey = 'score_result_id';

    public $timestamps = false;

    protected $fillable = [
        'public_response_session_id',
        'assessment_id',
        'overall_score',
        'calibration_status',
        'scoring_version',
        'input_hash',
        'result_hash',
        'payload',
        'calculated_at',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'payload' => 'array',
        'calculated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Respondent score results are immutable.'));
        static::deleting(fn () => throw new \LogicException('Respondent score results cannot be deleted independently.'));
    }

    public function responseSession(): BelongsTo
    {
        return $this->belongsTo(PublicResponseSession::class, 'public_response_session_id', 'session_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }
}
