<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAggregationResult extends Model
{
    use HasUuids;

    protected $primaryKey = 'aggregation_result_id';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'aggregation_method',
        'minimum_completed_respondents',
        'eligible_respondent_count',
        'excluded_session_count',
        'overall_score',
        'calibration_status',
        'scoring_version',
        'input_hash',
        'result_hash',
        'payload',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'payload' => 'array',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Final aggregation results are immutable.'));
        static::deleting(fn () => throw new \LogicException('Final aggregation results cannot be deleted independently.'));
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by', 'user_id');
    }
}
