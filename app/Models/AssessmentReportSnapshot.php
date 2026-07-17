<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentReportSnapshot extends Model
{
    use HasUuids;

    protected $primaryKey = 'report_snapshot_id';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id', 'schema_version', 'content_hash', 'payload', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Final report snapshots are immutable.'));
        static::deleting(fn () => throw new \LogicException('Final report snapshots cannot be deleted independently.'));
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }
}
