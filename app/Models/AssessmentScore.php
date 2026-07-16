<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScore extends Model
{
    protected $primaryKey = 'assessment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'overall_score',
        'maturity_level_id',
        'calibration_status',
        'clinical_quality_score',
        'expected_module_count',
        'active_module_count',
        'calculated_at',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'clinical_quality_score' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function maturityLevel(): BelongsTo
    {
        return $this->belongsTo(MaturityLevel::class, 'maturity_level_id', 'level_id');
    }

    public function isCalibrated(): bool
    {
        return $this->calibration_status === 'CALIBRATED';
    }

    public function isNotCalibrated(): bool
    {
        return $this->calibration_status === 'NOT_CALIBRATED' || $this->overall_score === null;
    }
}
