<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingType extends Model
{
    protected $primaryKey = 'setting_type_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['setting_type_code', 'setting_type_name', 'description', 'uses_departments', 'is_active', 'display_order'];

    protected $casts = ['uses_departments' => 'boolean', 'is_active' => 'boolean'];
}
