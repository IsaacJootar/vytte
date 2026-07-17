<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssessmentTemplateVersion extends Model
{
    use HasUuids;

    protected $primaryKey = 'template_version_id';

    protected $fillable = [
        'template_id', 'version_number', 'status', 'scoring_version',
        'content_hash', 'published_payload', 'parent_version_id', 'is_customized',
        'published_at', 'published_by',
    ];

    protected $casts = [
        'is_customized' => 'boolean',
        'published_payload' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->getOriginal('status') === 'PUBLISHED') {
                throw new \LogicException('Published template versions are immutable. Create a new version instead.');
            }
        });

        static::deleting(function (self $version) {
            if ($version->status === 'PUBLISHED') {
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
