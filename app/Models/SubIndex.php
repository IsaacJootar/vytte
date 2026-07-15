<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubIndex extends Model
{
    protected $primaryKey = 'sub_index_id';

    public $timestamps = false;

    protected $fillable = [
        'module_id',
        'domain_id',
        'acronym',
        'full_name',
        'description',
        'calculation_method',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id', 'domain_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'sub_index_questions', 'sub_index_id', 'question_id')
            ->withPivot('weight');
    }
}
