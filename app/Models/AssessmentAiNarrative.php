<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored AI narrative for one assessment, read through one lens.
 *
 * Held so the prose is not regenerated on every view, and stamped so a narrative written
 * from an older version of the report can be told apart from a current one.
 */
class AssessmentAiNarrative extends Model
{
    use HasUuids;

    protected $primaryKey = 'narrative_id';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id', 'lens', 'model', 'source_hash', 'body', 'generated_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by', 'user_id');
    }

    /**
     * Whether this narrative still matches the intelligence it was written from.
     */
    public function matches(string $currentSourceHash): bool
    {
        return $this->source_hash === $currentSourceHash;
    }
}
