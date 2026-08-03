<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentGovernanceClaim extends Model
{
    use HasUuids;

    public const CLAIM_TYPES = [
        'SOURCE_VERIFIED',
        'LICENCE_VERIFIED',
        'SUBJECT_REVIEWED',
        'METHODOLOGY_REVIEWED',
        'SCORING_REVIEWED',
        'FIELD_TESTED',
        'TRANSLATION_REVIEWED',
        'BENCHMARK_APPROVED',
    ];

    public const STATUSES = ['NOT_REVIEWED', 'PENDING', 'PASSED', 'FAILED', 'EXPIRED'];

    protected $primaryKey = 'content_governance_claim_id';

    protected $fillable = [
        'content_publisher_id',
        'content_type',
        'content_id',
        'claim_type',
        'status',
        'evidence_summary',
        'metadata',
        'reviewed_by',
        'reviewed_at',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(ContentPublisher::class, 'content_publisher_id', 'content_publisher_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }
}
