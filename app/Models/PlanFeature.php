<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['plan', 'feature_key', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
