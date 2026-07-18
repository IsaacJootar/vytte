<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionGroup extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $primaryKey = 'question_group_id';

    protected $fillable = [
        'module_id',
        'group_number',
        'group_label',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $group): void {
            if (! in_array($group->status, [self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
                throw new \LogicException("Unsupported question-group status: {$group->status}.");
            }
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'question_group_id', 'question_group_id')
            ->orderBy('display_order');
    }
}
