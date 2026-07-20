<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One coherent release of the official health knowledge model.
 *
 * Objectives, health areas, analysis lenses, insight categories, templates and presets
 * are published together, because objectives recommend the others. Versioning them
 * independently would let a published objective point at a lens that does not exist.
 *
 * Follows the same lifecycle as DomainTaxonomyVersion: draft, publish, freeze, supersede.
 */
class MethodologyVersion extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $primaryKey = 'methodology_version_id';

    protected $fillable = [
        'version_number', 'status', 'methodology_notes',
        'content_hash', 'parent_version_id', 'published_at', 'published_by',
    ];

    protected $casts = ['published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') !== self::STATUS_PUBLISHED) {
                return;
            }

            // A published methodology is what reports and recommendations were produced
            // against. Only its lifecycle status may move after publication.
            $disallowed = array_diff(array_keys($version->getDirty()), ['status', 'updated_at']);

            if ($disallowed !== []) {
                throw new \LogicException('Published methodology versions are immutable. Publish a new version instead.');
            }
        });
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(AssessmentObjective::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function healthAreas(): HasMany
    {
        return $this->hasMany(HealthArea::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function analysisLenses(): HasMany
    {
        return $this->hasMany(AnalysisLens::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function insightCategories(): HasMany
    {
        return $this->hasMany(InsightCategory::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(AssessmentTemplate::class, 'methodology_version_id', 'methodology_version_id');
    }

    public function presets(): HasMany
    {
        return $this->hasMany(ObjectivePreset::class, 'methodology_version_id', 'methodology_version_id');
    }
}
