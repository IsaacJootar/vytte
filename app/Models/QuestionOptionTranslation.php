<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOptionTranslation extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'translation_id';

    protected $fillable = ['option_id', 'locale', 'option_label'];

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'option_id', 'option_id');
    }
}
