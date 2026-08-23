<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A maturity band: a score range and the label a reader sees for it.
 *
 * `framework_version_id` is null for the platform-default five bands every assessment falls
 * back to. A framework may declare its own five rows instead — Odion Ikyo's "Vyttes maturity
 * bands" (Critical/Emerging/Developing/Operational/Advanced) are the first example — without
 * touching the defaults every other framework still relies on. See ScoringService for how a
 * framework-specific set takes priority when exactly one framework is in scope.
 */
class MaturityLevel extends Model
{
    protected $primaryKey = 'level_id';

    public $timestamps = false;

    protected $fillable = [
        'framework_version_id',
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

    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(DepartmentFrameworkVersion::class, 'framework_version_id', 'framework_version_id');
    }
}
