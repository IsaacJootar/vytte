<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionNumericBand extends Model
{
    protected $primaryKey = 'band_id';

    public $timestamps = false;

    protected $fillable = [
        'question_id',
        'min_value',
        'max_value',
        'score_weight',
        'band_order',
    ];

    protected $casts = [
        'min_value' => 'decimal:4',
        'max_value' => 'decimal:4',
        'score_weight' => 'decimal:2',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }
}
