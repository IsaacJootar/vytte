<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaturityLevel extends Model
{
    protected $primaryKey = 'level_id';

    public $timestamps = false;

    protected $fillable = [
        'level_number',
        'level_name',
        'min_score',
        'max_score',
        'description',
    ];

    protected $casts = [
        'min_score' => 'decimal:2',
        'max_score' => 'decimal:2',
    ];
}
