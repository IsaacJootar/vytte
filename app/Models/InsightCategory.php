<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The shape a finding takes in a report.
 *
 * Polarity says whether the finding is good, bad or neutral news, so a report can lead
 * with what matters rather than with an arbitrary order.
 *
 * `is_diagnostic` marks categories that point at a cause rather than describing a
 * symptom. Pain Points is the first of these. The platform already flags pain points at
 * option level through `question_options.is_flagged_pain_point` and already treats
 * critical failures in scoring, so this promotes an existing signal to a reportable
 * finding rather than inventing a new one.
 */
class InsightCategory extends Model
{
    use HasUuids;

    public const POLARITY_POSITIVE = 'POSITIVE';

    public const POLARITY_NEGATIVE = 'NEGATIVE';

    public const POLARITY_NEUTRAL = 'NEUTRAL';

    protected $primaryKey = 'insight_category_id';

    protected $fillable = [
        'methodology_version_id', 'category_code', 'category_name', 'polarity',
        'description', 'is_diagnostic', 'display_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'is_diagnostic' => 'boolean'];

    public function methodologyVersion(): BelongsTo
    {
        return $this->belongsTo(MethodologyVersion::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function polarityLabel(): string
    {
        return match ($this->polarity) {
            self::POLARITY_POSITIVE => 'Good news',
            self::POLARITY_NEGATIVE => 'Needs attention',
            default => 'Neutral',
        };
    }
}
