<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicResponseSession extends Model
{
    use HasUuids;

    protected $primaryKey = 'session_id';

    public $timestamps = false;

    protected $fillable = [
        'token',
        'assessment_id',
        'locale',
        'started_at',
        'last_activity_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(AssessmentRespondentToken::class, 'token', 'token');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class, 'public_response_session_id', 'session_id');
    }
}
