<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPublisher extends Model
{
    use HasUuids;

    public const TYPE_VYTTE = 'VYTTE';

    public const VISIBILITY_PRIVATE = 'PRIVATE';

    public const VISIBILITY_ORGANIZATION = 'ORGANIZATION';

    public const VISIBILITY_PARTNER = 'PARTNER';

    public const VISIBILITY_PUBLIC = 'PUBLIC';

    public const STATUS_UNVERIFIED = 'UNVERIFIED';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_VERIFIED = 'VERIFIED';

    public const STATUS_SUSPENDED = 'SUSPENDED';

    protected $primaryKey = 'content_publisher_id';

    protected $fillable = [
        'workspace_id',
        'publisher_code',
        'name',
        'publisher_type',
        'visibility',
        'verification_status',
        'attribution',
        'website_url',
        'contact_email',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'workspace_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }

    public function governanceClaims(): HasMany
    {
        return $this->hasMany(ContentGovernanceClaim::class, 'content_publisher_id', 'content_publisher_id');
    }
}
