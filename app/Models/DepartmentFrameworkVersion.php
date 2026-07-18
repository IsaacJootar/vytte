<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentFrameworkVersion extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $primaryKey = 'framework_version_id';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'module_id',
        'version_number',
        'status',
        'display_name',
        'description',
        'source_authority',
        'source_url',
        'license_code',
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
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if (! in_array($version->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true)) {
                throw new \LogicException("Unsupported framework-version status: {$version->status}.");
            }
        });

        static::updating(function (self $version): void {
            if ($version->getOriginal('status') === self::STATUS_PUBLISHED) {
                throw new \LogicException('Published department framework versions are immutable. Publish a new version instead.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->status === self::STATUS_PUBLISHED) {
                throw new \LogicException('Published department framework versions cannot be deleted.');
            }
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }
}
