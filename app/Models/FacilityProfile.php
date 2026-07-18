<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityProfile extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $primaryKey = 'facility_profile_id';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'profile_code',
        'profile_name',
        'setting_type_code',
        'description',
        'status',
        'display_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if (! in_array($profile->status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true)) {
                throw new \LogicException("Unsupported facility-profile status: {$profile->status}.");
            }
        });
    }

    public function settingType(): BelongsTo
    {
        return $this->belongsTo(SettingType::class, 'setting_type_code', 'setting_type_code');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            AssessmentModule::class,
            'facility_profile_departments',
            'facility_profile_id',
            'module_id'
        )->withPivot(['applicability', 'display_order', 'removal_allowed'])->orderByPivot('display_order');
    }

    public function catalogueReleases(): HasMany
    {
        return $this->hasMany(AssessmentCatalogueRelease::class, 'facility_profile_id', 'facility_profile_id');
    }
}
