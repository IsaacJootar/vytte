<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DomainTaxonomy extends Model
{
    use HasUuids;

    protected $primaryKey = 'domain_taxonomy_id';

    protected $fillable = [
        'taxonomy_code',
        'taxonomy_name',
        'description',
        'status',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(DomainTaxonomyVersion::class, 'domain_taxonomy_id', 'domain_taxonomy_id')
            ->orderByDesc('version_number');
    }
}
