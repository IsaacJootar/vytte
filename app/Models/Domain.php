<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $primaryKey = 'domain_id';

    public $timestamps = false;

    protected $fillable = [
        'domain_code',
        'domain_name',
        'is_operational',
        'display_order',
    ];

    protected $casts = [
        'is_operational' => 'boolean',
    ];

    public function subIndices(): HasMany
    {
        return $this->hasMany(SubIndex::class, 'domain_id', 'domain_id');
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(DomainDefinition::class, 'domain_id', 'domain_id');
    }
}
