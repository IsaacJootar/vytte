<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How results are interpreted.
 *
 * A lens holds no score and changes no score. It selects and frames what has already
 * been measured, which is why one assessment legitimately produces different insight
 * and different recommendations under different lenses.
 *
 * Distinct from a Measurement Domain (the `domains` table), which is a dimension that
 * scores roll up into. Executive Summary is a valid lens; it could never be a
 * measurement domain, because nothing rolls up into it.
 */
class AnalysisLens extends Model
{
    use HasUuids;

    protected $primaryKey = 'analysis_lens_id';

    protected $fillable = [
        'methodology_version_id', 'lens_code', 'lens_name',
        'question_it_answers', 'description', 'display_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function methodologyVersion(): BelongsTo
    {
        return $this->belongsTo(MethodologyVersion::class, 'methodology_version_id', 'methodology_version_id');
    }
}
