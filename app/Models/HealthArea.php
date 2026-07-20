<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subdivision of a health domain.
 *
 * Health domains were flat, so HIV could not distinguish testing from treatment from
 * prevention. Areas give the catalogue enough resolution to recommend content without
 * multiplying top-level domains.
 */
class HealthArea extends Model
{
    use HasUuids;

    protected $primaryKey = 'health_area_id';

    protected $fillable = [
        'methodology_version_id', 'health_domain_id', 'area_code',
        'area_name', 'description', 'display_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function methodologyVersion(): BelongsTo
    {
        return $this->belongsTo(MethodologyVersion::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function healthDomain(): BelongsTo
    {
        return $this->belongsTo(HealthDomain::class, 'health_domain_id', 'health_domain_id');
    }
}
