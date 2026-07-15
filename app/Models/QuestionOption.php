<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $primaryKey = 'option_id';

    public $timestamps = false;

    protected $fillable = [
        'question_id',
        'option_label',
        'option_order',
        'score_weight',
        'is_flagged_pain_point',
    ];

    protected $casts = [
        'score_weight' => 'decimal:2',
        'is_flagged_pain_point' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }
}
