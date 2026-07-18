<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssessmentCatalogueRelease extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $primaryKey = 'catalogue_release_id';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'release_code',
        'release_name',
        'description',
        'creation_path',
        'facility_profile_id',
        'health_domain_id',
        'status',
        'aggregation_policy',
        'composition_rules',
        'content_hash',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'aggregation_policy' => 'array',
        'composition_rules' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $release): void {
            if (! in_array($release->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true)) {
                throw new \LogicException("Unsupported catalogue-release status: {$release->status}.");
            }

            if (! in_array($release->creation_path, ['COMPREHENSIVE', 'FOCUSED'], true)) {
                throw new \LogicException("Unsupported catalogue creation path: {$release->creation_path}.");
            }
        });

        static::updating(function (self $release): void {
            if ($release->getOriginal('status') === self::STATUS_PUBLISHED) {
                throw new \LogicException('Published catalogue releases are immutable. Publish a new release instead.');
            }
        });
    }

    public function facilityProfile(): BelongsTo
    {
        return $this->belongsTo(FacilityProfile::class, 'facility_profile_id', 'facility_profile_id');
    }

    public function healthDomain(): BelongsTo
    {
        return $this->belongsTo(HealthDomain::class, 'health_domain_id', 'health_domain_id');
    }

    public function departmentFrameworkVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            DepartmentFrameworkVersion::class,
            'assessment_catalogue_department_versions',
            'catalogue_release_id',
            'framework_version_id'
        )->withPivot(['module_id', 'applicability', 'display_order', 'area_label'])->orderByPivot('display_order');
    }
}
