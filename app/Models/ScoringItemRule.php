<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringItemRule extends Model
{
    use HasUuids;

    protected $primaryKey = 'scoring_item_rule_id';

    protected $fillable = [
        'scoring_model_version_id', 'framework_question_placement_id', 'question_version_id',
        'sub_index_id', 'method', 'score_role', 'weight', 'criticality', 'rule_config',
    ];

    protected $casts = ['weight' => 'decimal:3', 'rule_config' => 'array'];

    protected static function booted(): void
    {
        $assertDraft = function (self $rule): void {
            if ($rule->scoringModelVersion?->status === ScoringModelVersion::STATUS_PUBLISHED) {
                throw new \LogicException('Rules in a published scoring model are immutable.');
            }
        };
        static::creating($assertDraft);
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function scoringModelVersion(): BelongsTo
    {
        return $this->belongsTo(ScoringModelVersion::class, 'scoring_model_version_id', 'scoring_model_version_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(FrameworkQuestionPlacement::class, 'framework_question_placement_id', 'framework_question_placement_id');
    }
}
