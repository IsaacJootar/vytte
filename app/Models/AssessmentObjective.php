<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Why an assessment is being run.
 *
 * Purposes only. The subject lives in health domains, so "Malaria" is not an objective;
 * a Baseline applied to Malaria is. Keeping the two apart stops the same concept
 * existing twice and keeps objective mapping acyclic.
 */
class AssessmentObjective extends Model
{
    use HasUuids;

    /** Broad families used to group the catalogue for a reader. */
    public const GROUPS = [
        'PURPOSE' => 'Why you are assessing',
        'LIFECYCLE' => 'Where you are in a programme',
        'ASSURANCE' => 'Compliance and accreditation',
        'IMPROVEMENT' => 'Improving performance',
        'SYSTEM' => 'Health system function',
    ];

    protected $primaryKey = 'assessment_objective_id';

    protected $fillable = [
        'methodology_version_id', 'objective_code', 'objective_name', 'objective_group',
        'description', 'question_it_answers', 'display_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function methodologyVersion(): BelongsTo
    {
        return $this->belongsTo(MethodologyVersion::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(ObjectiveRecommendation::class, 'assessment_objective_id', 'assessment_objective_id');
    }

    public function presets(): HasMany
    {
        return $this->hasMany(ObjectivePreset::class, 'assessment_objective_id', 'assessment_objective_id');
    }

    public function groupLabel(): string
    {
        return self::GROUPS[$this->objective_group] ?? $this->objective_group;
    }
}
