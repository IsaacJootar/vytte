<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentTier extends Model
{
    protected $primaryKey = 'assessment_tier_id';

    public $timestamps = false;

    protected $fillable = [
        'tier_code',
        'tier_name',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'assessment_tier_id', 'assessment_tier_id');
    }
}
