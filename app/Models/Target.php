<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Target extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected $primaryKey = 'target_id';

    protected $fillable = [
        'owner_workspace_id',
        'target_type_code',
        'name',
        'country',
        'region',
        'sub_region',
        'ownership',
        'custom_setting_label',
        'uses_departments',
        'facility_profile_id',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'uses_departments' => 'boolean',
    ];

    public function getWorkspaceForeignKey(): string
    {
        return 'owner_workspace_id';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'owner_workspace_id', 'workspace_id');
    }

    public function targetType(): BelongsTo
    {
        return $this->belongsTo(TargetType::class, 'target_type_code', 'target_type_code');
    }

    public function facilityProfile(): BelongsTo
    {
        return $this->belongsTo(FacilityProfile::class, 'facility_profile_id', 'facility_profile_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_targets', 'target_id', 'project_id')
            ->withPivot('added_at');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'target_id', 'target_id');
    }
}
