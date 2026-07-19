<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FrameworkSection extends Model
{
    use HasUuids;

    protected $primaryKey = 'framework_section_id';

    protected $fillable = [
        'framework_version_id',
        'section_code',
        'section_name',
        'purpose',
        'display_order',
    ];

    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(DepartmentFrameworkVersion::class, 'framework_version_id', 'framework_version_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(FrameworkIndicator::class, 'framework_section_id', 'framework_section_id')
            ->orderBy('display_order');
    }

    public function questionPlacements(): HasMany
    {
        return $this->hasMany(FrameworkQuestionPlacement::class, 'framework_section_id', 'framework_section_id')
            ->orderBy('display_order');
    }
}
