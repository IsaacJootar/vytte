<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentModule extends Model
{
    protected $primaryKey = 'module_id';

    public $timestamps = false;

    protected $fillable = [
        'target_type_code',
        'module_code',
        'module_name',
        'primary_respondent',
        'estimated_duration_minutes',
        'data_collection_methods',
        'is_active',
        'requires_consent',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_consent' => 'boolean',
    ];

    public function targetType(): BelongsTo
    {
        return $this->belongsTo(TargetType::class, 'target_type_code', 'target_type_code');
    }

    public function questionGroups(): HasMany
    {
        return $this->hasMany(QuestionGroup::class, 'module_id', 'module_id')
            ->orderBy('group_number');
    }

    public function subIndices(): HasMany
    {
        return $this->hasMany(SubIndex::class, 'module_id', 'module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'module_id', 'module_id')
            ->orderBy('display_order');
    }

    /**
     * Health areas this department covers, used to suggest the health area when an
     * assessment is published as a focused catalogue release.
     */
    public function healthDomains(): BelongsToMany
    {
        return $this->belongsToMany(
            HealthDomain::class,
            'assessment_module_health_domain',
            'module_id',
            'health_domain_id'
        );
    }

    public function frameworkVersions(): HasMany
    {
        return $this->hasMany(DepartmentFrameworkVersion::class, 'module_id', 'module_id')
            ->orderByDesc('version_number');
    }

    public function getRouteKeyName(): string
    {
        return 'module_id';
    }
}
