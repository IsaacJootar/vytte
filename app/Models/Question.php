<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasUuids;

    protected $primaryKey = 'question_id';

    public $timestamps = false;

    protected $fillable = [
        'module_id',
        'module_domain_id',
        'question_number',
        'question_code',
        'question_text',
        'type_id',
        'requires_observation',
        'respondent_role_hint',
        'display_order',
        'is_active',
        'is_scored',
        'source',
        'question_status',
        'standard_reference_id',
        'standard_alignment_status',
        'corroborates_sub_index_id',
        'numeric_unit',
        'numeric_min',
        'numeric_max',
        'numeric_step',
    ];

    protected $casts = [
        'requires_observation' => 'boolean',
        'is_active' => 'boolean',
        'is_scored' => 'boolean',
        'numeric_min' => 'decimal:4',
        'numeric_max' => 'decimal:4',
        'numeric_step' => 'decimal:4',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(AssessmentModule::class, 'module_id', 'module_id');
    }

    public function questionType(): BelongsTo
    {
        return $this->belongsTo(QuestionType::class, 'type_id', 'type_id');
    }

    public function moduleDomain(): BelongsTo
    {
        return $this->belongsTo(ModuleDomain::class, 'module_domain_id', 'module_domain_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id', 'question_id')
            ->orderBy('option_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class, 'question_id', 'question_id')
            ->orderBy('version_number');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(FrameworkQuestionPlacement::class, 'question_id', 'question_id');
    }

    public function numericBands(): HasMany
    {
        return $this->hasMany(QuestionNumericBand::class, 'question_id', 'question_id')
            ->orderBy('band_order');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class, 'question_id', 'question_id');
    }

    public function subIndices(): BelongsToMany
    {
        return $this->belongsToMany(SubIndex::class, 'sub_index_questions', 'question_id', 'sub_index_id')
            ->withPivot('weight');
    }
}
