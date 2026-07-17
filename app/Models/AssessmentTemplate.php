<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentTemplate extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $primaryKey = 'template_id';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'template_code', 'template_name', 'description', 'creation_path',
        'setting_type_code', 'health_domain_id', 'source_authority',
        'source_url', 'license_code', 'status', 'created_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            if (! in_array($template->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true)) {
                throw new \LogicException("Unsupported template status: {$template->status}.");
            }

            if ($template->exists
                && $template->isDirty('status')
                && ! ($template->getOriginal('status') === self::STATUS_DRAFT && $template->status === self::STATUS_PUBLISHED)
            ) {
                throw new \LogicException('Templates can only transition from DRAFT to PUBLISHED.');
            }
        });
    }

    public function healthDomain(): BelongsTo
    {
        return $this->belongsTo(HealthDomain::class, 'health_domain_id', 'health_domain_id');
    }

    public function settingType(): BelongsTo
    {
        return $this->belongsTo(SettingType::class, 'setting_type_code', 'setting_type_code');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssessmentTemplateVersion::class, 'template_id', 'template_id');
    }
}
