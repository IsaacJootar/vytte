<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentFrameworkVersion extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    public const TYPE_DEPARTMENT = 'DEPARTMENT';

    public const TYPE_FOCUSED = 'FOCUSED';

    protected $primaryKey = 'framework_version_id';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'framework_type' => self::TYPE_DEPARTMENT,
    ];

    protected $fillable = [
        'module_id',
        'framework_type',
        'version_number',
        'status',
        'display_name',
        'description',
        'purpose',
        'source_authority',
        'source_url',
        'license_code',
        'methodology_notes',
        'source_summary',
        'review_notes',
        'reviewed_by',
        'approved_by',
        'effective_date',
        'provenance',
        'evidence_requirements',
        'critical_failure_rules',
        'scoring_version',
        'content_hash',
        'published_payload',
        'parent_version_id',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'provenance' => 'array',
        'evidence_requirements' => 'array',
        'critical_failure_rules' => 'array',
        'published_payload' => 'array',
        'effective_date' => 'date',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if (! in_array($version->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED, self::STATUS_SUPERSEDED], true)) {
                throw new \LogicException("Unsupported framework-version status: {$version->status}.");
            }

            if (! in_array($version->framework_type, [self::TYPE_DEPARTMENT, self::TYPE_FOCUSED], true)) {
                throw new \LogicException("Unsupported framework type: {$version->framework_type}.");
            }
        });

        static::updating(function (self $version): void {
            if (in_array($version->getOriginal('status'), [self::STATUS_PUBLISHED, self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true)) {
                $dirty = array_keys($version->getDirty());
                $disallowed = array_diff($dirty, ['status', 'updated_at']);
                $statusTransitionAllowed = $version->getOriginal('status') === self::STATUS_PUBLISHED
                    && in_array($version->status, [self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true);

                if ($disallowed !== [] || ! $statusTransitionAllowed) {
                    throw new \LogicException('Published, superseded, and archived framework versions are immutable. Publish a successor draft instead.');
                }
            }
        });

        static::deleting(function (self $version): void {
            if (in_array($version->status, [self::STATUS_PUBLISHED, self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true)) {
                throw new \LogicException('Published, superseded, and archived framework versions cannot be deleted.');
            }
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FrameworkSection::class, 'framework_version_id', 'framework_version_id')
            ->orderBy('display_order');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(FrameworkIndicator::class, 'framework_version_id', 'framework_version_id')
            ->orderBy('display_order');
    }

    public function questionPlacements(): HasMany
    {
        return $this->hasMany(FrameworkQuestionPlacement::class, 'framework_version_id', 'framework_version_id')
            ->orderBy('display_order');
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id', 'framework_version_id');
    }

    public function catalogueReleases(): BelongsToMany
    {
        return $this->belongsToMany(
            AssessmentCatalogueRelease::class,
            'assessment_catalogue_department_versions',
            'framework_version_id',
            'catalogue_release_id'
        )->withPivot(['module_id', 'applicability', 'display_order', 'area_label']);
    }
}
