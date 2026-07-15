<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetCategory extends Model
{
    protected $primaryKey = 'category_id';

    public $incrementing = true;

    public $timestamps = false;

    public function targetType(): BelongsTo
    {
        return $this->belongsTo(TargetType::class, 'target_type_code', 'target_type_code');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class, 'category_id', 'category_id');
    }
}
