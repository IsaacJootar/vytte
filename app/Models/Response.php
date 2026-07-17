<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Response extends Model
{
    use HasUuids;

    protected $primaryKey = 'response_id';

    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'question_id',
        'respondent_id',
        'public_response_session_id',
        'value_text',
        'value_numeric',
        'value_option_id',
        'evidence_note',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'value_numeric' => 'decimal:4',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'value_option_id', 'option_id');
    }

    public function publicResponseSession(): BelongsTo
    {
        return $this->belongsTo(PublicResponseSession::class, 'public_response_session_id', 'session_id');
    }
}
