<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentShareLink extends Model
{
    use HasUuids;

    protected $primaryKey = 'link_id';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id', 'module_id', 'topic_id', 'token', 'created_by',
        'created_at', 'expires_at', 'is_active', 'last_used_at', 'use_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
