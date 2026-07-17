<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentModuleScope extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_EXCLUDED = 'EXCLUDED';

    protected $table = 'assessment_module_scope';

    protected $primaryKey = 'assessment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'module_id',
        'in_scope',
        'is_category_default',
        'exclusion_reason',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'in_scope' => 'boolean',
        'is_category_default' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }
}
