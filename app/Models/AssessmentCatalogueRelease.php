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

    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    protected $primaryKey = 'catalogue_release_id';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'release_code',
        'parent_release_id',
        'release_name',
        'description',
        'creation_path',
        'facility_profile_id',
        'health_domain_id',
        'status',
        'aggregation_policy',
        'composition_rules',
        'collection_config',
        'content_hash',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'aggregation_policy' => 'array',
        'composition_rules' => 'array',
        'collection_config' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $release): void {
            if (! in_array($release->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED, self::STATUS_SUPERSEDED], true)) {
                throw new \LogicException("Unsupported catalogue-release status: {$release->status}.");
            }

            if (! in_array($release->creation_path, ['COMPREHENSIVE', 'FOCUSED'], true)) {
                throw new \LogicException("Unsupported catalogue creation path: {$release->creation_path}.");
            }
        });

        static::updating(function (self $release): void {
            if (in_array($release->getOriginal('status'), [self::STATUS_PUBLISHED, self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true)) {
                $dirty = array_keys($release->getDirty());
                $disallowed = array_diff($dirty, ['status', 'updated_at']);
                $statusTransitionAllowed = $release->getOriginal('status') === self::STATUS_PUBLISHED
                    && in_array($release->status, [self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true);

                if ($disallowed !== [] || ! $statusTransitionAllowed) {
                    throw new \LogicException('Published, superseded, and archived catalogue releases are immutable. Publish a successor release instead.');
                }
            }
        });

        static::deleting(function (self $release): void {
            if (in_array($release->status, [self::STATUS_PUBLISHED, self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true)) {
                throw new \LogicException('Published, superseded, and archived catalogue releases cannot be deleted.');
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

    public function parentRelease(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_release_id', 'catalogue_release_id');
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
