<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An official starting point for building an assessment.
 *
 * A template is a recommendation, never a constraint. It suggests scope and content;
 * the builder, publication validation and scoring remain the only authorities on what
 * may actually be published.
 *
 * ENTERPRISE and FOCUSED differ only in breadth. Both run through the same builder,
 * scoring and reporting with no duplicated logic. See Part 8.
 */
class AssessmentTemplate extends Model
{
    use HasUuids;

    public const SCOPE_ENTERPRISE = 'ENTERPRISE';

    public const SCOPE_FOCUSED = 'FOCUSED';

    protected $primaryKey = 'assessment_template_id';

    protected $fillable = [
        'methodology_version_id', 'template_code', 'template_name', 'description',
        'scope_type', 'target_type_code', 'typical_duration_minutes', 'display_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function methodologyVersion(): BelongsTo
    {
        return $this->belongsTo(MethodologyVersion::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function scopeLabel(): string
    {
        return $this->scope_type === self::SCOPE_ENTERPRISE ? 'Whole organisation' : 'One subject area';
    }
}
