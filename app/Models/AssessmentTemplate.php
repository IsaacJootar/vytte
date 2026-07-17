<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentTemplate extends Model
{
    use HasUuids;

    protected $primaryKey = 'template_id';

    protected $fillable = [
        'template_code', 'template_name', 'description', 'creation_path',
        'setting_type_code', 'health_domain_id', 'source_authority',
        'source_url', 'license_code', 'status', 'created_by',
    ];

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
