<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved starting combination, such as "Malaria Baseline Assessment".
 *
 * Not a new concept in the knowledge model. A preset preselects an objective, its
 * health domains, a template and a set of lenses, so a user can start from a familiar
 * name without Malaria needing to exist as both an objective and a health domain.
 */
class ObjectivePreset extends Model
{
    use HasUuids;

    protected $primaryKey = 'objective_preset_id';

    protected $fillable = [
        'methodology_version_id', 'assessment_objective_id', 'preset_code', 'preset_name',
        'description', 'health_domain_codes', 'template_code', 'analysis_lens_codes',
        'display_order', 'is_active',
    ];

    protected $casts = [
        'health_domain_codes' => 'array',
        'analysis_lens_codes' => 'array',
        'is_active' => 'boolean',
    ];

    public function methodologyVersion(): BelongsTo
    {
        return $this->belongsTo(MethodologyVersion::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(AssessmentObjective::class, 'assessment_objective_id', 'assessment_objective_id');
    }
}
