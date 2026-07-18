<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $primaryKey = 'plan_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'plan_code',
        'plan_name',
        'public_label',
        'description',
        'display_order',
        'is_active',
        'is_beta_unlocked',
        'pricing_metadata',
        'limits',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_beta_unlocked' => 'boolean',
        'pricing_metadata' => 'array',
        'limits' => 'array',
    ];
}
