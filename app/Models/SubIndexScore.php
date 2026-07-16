<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubIndexScore extends Model
{
    protected $table = 'sub_index_scores';

    // Composite PK — declare the first column so Eloquent doesn't break
    protected $primaryKey = 'assessment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'sub_index_id',
        'respondent_type',
        'score',
        'calibration_status',
        'confidence_tier',
        'calculated_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function subIndex(): BelongsTo
    {
        return $this->belongsTo(SubIndex::class, 'sub_index_id', 'sub_index_id');
    }
}
