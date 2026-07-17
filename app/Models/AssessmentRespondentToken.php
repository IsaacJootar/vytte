<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentRespondentToken extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'token';

    protected $fillable = [
        'token',
        'assessment_id',
        'created_by',
        'expires_at',
        'revoked_at',
        'use_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function responseSessions(): HasMany
    {
        return $this->hasMany(PublicResponseSession::class, 'token', 'token');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
