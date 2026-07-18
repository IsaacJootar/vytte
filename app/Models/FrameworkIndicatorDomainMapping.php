<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameworkIndicatorDomainMapping extends Model
{
    use HasUuids;

    protected $primaryKey = 'indicator_domain_mapping_id';

    protected $fillable = [
        'framework_indicator_id',
        'domain_definition_id',
        'is_primary',
        'contribution_weight',
        'rationale',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'contribution_weight' => 'decimal:3',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(FrameworkIndicator::class, 'framework_indicator_id', 'framework_indicator_id');
    }

    public function domainDefinition(): BelongsTo
    {
        return $this->belongsTo(DomainDefinition::class, 'domain_definition_id', 'domain_definition_id');
    }
}
