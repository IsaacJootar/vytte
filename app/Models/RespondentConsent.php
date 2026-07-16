<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespondentConsent extends Model
{
    use HasUuids;

    protected $primaryKey = 'consent_id';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'module_id',
        'consent_text',
        'consented_by',
        'consented_at',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }

    public function consentedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consented_by', 'user_id');
    }
}
