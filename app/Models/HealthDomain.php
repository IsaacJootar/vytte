<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HealthDomain extends Model
{
    protected $primaryKey = 'health_domain_id';

    public $timestamps = false;

    protected $fillable = ['domain_code', 'domain_name', 'description', 'is_active', 'display_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentModule::class, 'assessment_module_health_domain', 'health_domain_id', 'module_id')
            ->withPivot('is_primary');
    }
}
