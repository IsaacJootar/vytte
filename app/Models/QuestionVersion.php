<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionVersion extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_INTERNAL_REVIEW = 'INTERNAL_REVIEW';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    protected $primaryKey = 'question_version_id';

    protected $fillable = [
        'question_id',
        'parent_version_id',
        'version_number',
        'status',
        'question_text',
        'type_id',
        'options',
        'numeric_config',
        'numeric_bands',
        'requires_observation',
        'respondent_role_hint',
        'methodology_notes',
        'source_summary',
        'review_notes',
        'reviewed_by',
        'approved_by',
        'effective_date',
        'content_hash',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'options' => 'array',
        'numeric_config' => 'array',
        'numeric_bands' => 'array',
        'requires_observation' => 'boolean',
        'effective_date' => 'date',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if (! in_array($version->status, [
                self::STATUS_DRAFT,
                self::STATUS_INTERNAL_REVIEW,
                self::STATUS_APPROVED,
                self::STATUS_PUBLISHED,
                self::STATUS_ARCHIVED,
                self::STATUS_SUPERSEDED,
            ], true)) {
                throw new \LogicException("Unsupported question-version status: {$version->status}.");
            }
        });

        static::updating(function (self $version): void {
            if (in_array($version->getOriginal('status'), [self::STATUS_PUBLISHED, self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true)) {
                $dirty = array_keys($version->getDirty());
                $disallowed = array_diff($dirty, ['status', 'updated_at']);
                $statusTransitionAllowed = $version->getOriginal('status') === self::STATUS_PUBLISHED
                    && in_array($version->status, [self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true);

                if ($disallowed !== [] || ! $statusTransitionAllowed) {
                    throw new \LogicException('Published, superseded, and archived question versions are immutable. Create a successor draft instead.');
                }
            }
        });

        static::deleting(function (self $version): void {
            if (in_array($version->status, [self::STATUS_PUBLISHED, self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true)) {
                throw new \LogicException('Published, superseded, and archived question versions cannot be deleted.');
            }
        });
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }

    public function questionType(): BelongsTo
    {
        return $this->belongsTo(QuestionType::class, 'type_id', 'type_id');
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id', 'question_version_id');
    }
}
