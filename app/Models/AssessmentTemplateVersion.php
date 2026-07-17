<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssessmentTemplateVersion extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $primaryKey = 'template_version_id';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'template_id', 'version_number', 'status', 'scoring_version',
        'content_hash', 'published_payload', 'parent_version_id', 'is_customized',
        'published_at', 'published_by', 'allows_multi_respondent',
        'minimum_completed_respondents', 'aggregation_method',
        'respondent_eligibility_rules',
    ];

    protected $casts = [
        'is_customized' => 'boolean',
        'published_payload' => 'array',
        'allows_multi_respondent' => 'boolean',
        'respondent_eligibility_rules' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if (! in_array($version->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true)) {
                throw new \LogicException("Unsupported template-version status: {$version->status}.");
            }
        });

        static::updating(function (self $version) {
            if ($version->getOriginal('status') === self::STATUS_PUBLISHED) {
                throw new \LogicException('Published template versions are immutable. Create a new version instead.');
            }
        });

        static::deleting(function (self $version) {
            if ($version->status === self::STATUS_PUBLISHED) {
                throw new \LogicException('Published template versions cannot be deleted. Retire the template instead.');
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class, 'template_id', 'template_id');
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(
            AssessmentModule::class,
            'assessment_template_version_modules',
            'template_version_id',
            'module_id'
        )->withPivot(['display_order', 'is_default', 'area_label'])->orderByPivot('display_order');
    }
}
