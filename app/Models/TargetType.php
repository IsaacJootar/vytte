<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetType extends Model
{
    protected $primaryKey = 'target_type_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    public function categories(): HasMany
    {
        return $this->hasMany(TargetCategory::class, 'target_type_code', 'target_type_code');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class, 'target_type_code', 'target_type_code');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(AssessmentModule::class, 'target_type_code', 'target_type_code');
    }
}
