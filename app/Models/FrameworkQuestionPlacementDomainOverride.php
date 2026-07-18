<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameworkQuestionPlacementDomainOverride extends Model
{
    use HasUuids;

    protected $primaryKey = 'placement_domain_override_id';

    protected $fillable = [
        'framework_question_placement_id',
        'domain_definition_id',
        'is_primary',
        'contribution_weight',
        'rationale',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'contribution_weight' => 'decimal:3',
    ];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(FrameworkQuestionPlacement::class, 'framework_question_placement_id', 'framework_question_placement_id');
    }

    public function domainDefinition(): BelongsTo
    {
        return $this->belongsTo(DomainDefinition::class, 'domain_definition_id', 'domain_definition_id');
    }
}
