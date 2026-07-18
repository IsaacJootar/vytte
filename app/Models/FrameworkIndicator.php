<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FrameworkIndicator extends Model
{
    use HasUuids;

    protected $primaryKey = 'framework_indicator_id';

    protected $fillable = [
        'framework_version_id',
        'framework_section_id',
        'indicator_code',
        'indicator_name',
        'description',
        'display_order',
    ];

    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(DepartmentFrameworkVersion::class, 'framework_version_id', 'framework_version_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FrameworkSection::class, 'framework_section_id', 'framework_section_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(FrameworkQuestionPlacement::class, 'framework_indicator_id', 'framework_indicator_id')
            ->orderBy('display_order');
    }

    public function domainMappings(): HasMany
    {
        return $this->hasMany(FrameworkIndicatorDomainMapping::class, 'framework_indicator_id', 'framework_indicator_id');
    }
}
