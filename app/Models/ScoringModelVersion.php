<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringModelVersion extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $primaryKey = 'scoring_model_version_id';

    protected $fillable = [
        'scoring_model_id', 'framework_version_id', 'version_number', 'status', 'score_purpose',
        'construct', 'direction', 'algorithm_version', 'missing_policy', 'aggregation_config',
        'content_hash', 'published_at', 'published_by',
    ];

    protected $casts = [
        'missing_policy' => 'array',
        'aggregation_config' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') === self::STATUS_PUBLISHED) {
                throw new \LogicException('Published scoring-model versions are immutable.');
            }
        });
        static::deleting(function (self $version): void {
            if ($version->status === self::STATUS_PUBLISHED) {
                throw new \LogicException('Published scoring-model versions cannot be deleted.');
            }
        });
    }

    public function scoringModel(): BelongsTo
    {
        return $this->belongsTo(ScoringModel::class, 'scoring_model_id', 'scoring_model_id');
    }

    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(DepartmentFrameworkVersion::class, 'framework_version_id', 'framework_version_id');
    }

    public function itemRules(): HasMany
    {
        return $this->hasMany(ScoringItemRule::class, 'scoring_model_version_id', 'scoring_model_version_id');
    }
}
