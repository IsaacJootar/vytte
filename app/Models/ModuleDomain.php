<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleDomain extends Model
{
    protected $primaryKey = 'module_domain_id';

    public $timestamps = false;

    protected $fillable = [
        'module_id',
        'domain_number',
        'domain_label',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'module_domain_id', 'module_domain_id')
            ->orderBy('display_order');
    }
}
