<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalCustomSection extends Model
{
    use HasUuids;

    protected $primaryKey = 'local_section_id';

    protected $fillable = [
        'assessment_id',
        'workspace_id',
        'section_title',
        'instructions',
        'questions',
        'answers',
        'respondent_answers',
        'custom_score',
        'scored_at',
        'created_by',
    ];

    protected $casts = [
        'questions' => 'array',
        'answers' => 'array',
        'respondent_answers' => 'array',
        'scored_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $section): void {
            if (! $section->assessment()->firstOrFail()->isDraft()) {
                throw new \LogicException('Local questions cannot be added after response collection has opened.');
            }
        });

        static::updating(function (self $section): void {
            if ($section->isDirty(['section_title', 'instructions', 'questions'])
                && ! $section->assessment()->firstOrFail()->isDraft()) {
                throw new \LogicException('Local questions cannot be changed after response collection has opened.');
            }
        });

        static::deleting(function (self $section): void {
            if (! $section->assessment()->firstOrFail()->isDraft()) {
                throw new \LogicException('Local questions cannot be removed after response collection has opened.');
            }
        });
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'workspace_id');
    }
}
