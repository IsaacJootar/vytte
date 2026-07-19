<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSnapshot extends Model
{
    use HasUuids;

    protected $primaryKey = 'snapshot_id';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id', 'creation_path',
        'catalogue_release_id', 'facility_profile_id', 'setting_type_code',
        'health_domain_id', 'content_hash', 'is_customized',
        'composition_manifest', 'aggregation_policy', 'payload',
        'collection_config', 'created_by', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'composition_manifest' => 'array',
        'aggregation_policy' => 'array',
        'collection_config' => 'array',
        'is_customized' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException(
                'Assessment snapshots are immutable. The composed content, manifest, aggregation policy and collection config are frozen at creation and must never change.'
            );
        });
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }
}
