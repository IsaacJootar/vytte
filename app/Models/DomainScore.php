<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainScore extends Model
{
    protected $table = 'domain_scores';

    // Composite PK — declare the first column so Eloquent doesn't break
    protected $primaryKey = 'assessment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'domain_id',
        'domain_taxonomy_version_id',
        'domain_taxonomy_content_hash',
        'score',
        'calibration_status',
        'questions_expected',
        'questions_answered',
        'contributing_question_trace',
        'calculated_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'contributing_question_trace' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id', 'domain_id');
    }
}
