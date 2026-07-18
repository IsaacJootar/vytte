<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DomainDefinition extends Model
{
    use HasUuids;

    protected $primaryKey = 'domain_definition_id';

    protected $fillable = [
        'domain_taxonomy_version_id',
        'domain_id',
        'domain_code',
        'domain_name',
        'definition',
        'rationale',
        'display_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $definition): void {
            $definition->loadMissing('taxonomyVersion');
            if ($definition->taxonomyVersion?->status === DomainTaxonomyVersion::STATUS_PUBLISHED) {
                throw new \LogicException('Definitions inside a published domain taxonomy version are immutable.');
            }
        });

        static::deleting(function (self $definition): void {
            $definition->loadMissing('taxonomyVersion');
            if ($definition->taxonomyVersion?->status === DomainTaxonomyVersion::STATUS_PUBLISHED) {
                throw new \LogicException('Definitions inside a published domain taxonomy version cannot be deleted.');
            }
        });
    }

    public function taxonomyVersion(): BelongsTo
    {
        return $this->belongsTo(DomainTaxonomyVersion::class, 'domain_taxonomy_version_id', 'domain_taxonomy_version_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id', 'domain_id');
    }

    public function indicatorMappings(): HasMany
    {
        return $this->hasMany(FrameworkIndicatorDomainMapping::class, 'domain_definition_id', 'domain_definition_id');
    }
}
