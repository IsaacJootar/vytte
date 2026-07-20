<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an objective suggests.
 *
 * Suggestions only. Nothing here restricts what an author may build or publish; it
 * shortens the path to a sensible starting point.
 *
 * Stored as a loose reference by code rather than a foreign key, because the referenced
 * entity lives across several tables and some, such as evidence types, are not tables.
 */
class ObjectiveRecommendation extends Model
{
    use HasUuids;

    public const TYPES = [
        'HEALTH_DOMAIN' => 'Health domain',
        'HEALTH_AREA' => 'Health area',
        'TEMPLATE' => 'Template',
        'ANALYSIS_LENS' => 'Analysis lens',
        'MEASUREMENT_DOMAIN' => 'Measurement domain',
        'EVIDENCE_TYPE' => 'Evidence type',
    ];

    protected $primaryKey = 'objective_recommendation_id';

    protected $fillable = [
        'assessment_objective_id', 'recommends_type', 'recommends_ref', 'display_order', 'rationale',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(AssessmentObjective::class, 'assessment_objective_id', 'assessment_objective_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->recommends_type] ?? $this->recommends_type;
    }
}
