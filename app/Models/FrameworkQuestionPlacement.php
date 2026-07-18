<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FrameworkQuestionPlacement extends Model
{
    use HasUuids;

    protected $primaryKey = 'framework_question_placement_id';

    protected $fillable = [
        'framework_version_id',
        'framework_section_id',
        'framework_indicator_id',
        'question_id',
        'question_version_id',
        'sub_index_id',
        'display_order',
        'is_required',
        'applicability',
        'evidence_expectation',
        'weight',
        'scoring_contribution',
        'criticality',
        'help_text',
        'local_display_text',
        'metadata',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'applicability' => 'array',
        'weight' => 'decimal:3',
        'scoring_contribution' => 'boolean',
        'metadata' => 'array',
    ];

    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(DepartmentFrameworkVersion::class, 'framework_version_id', 'framework_version_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FrameworkSection::class, 'framework_section_id', 'framework_section_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(FrameworkIndicator::class, 'framework_indicator_id', 'framework_indicator_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'question_version_id', 'question_version_id');
    }

    public function subIndex(): BelongsTo
    {
        return $this->belongsTo(SubIndex::class, 'sub_index_id', 'sub_index_id');
    }

    public function domainOverrides(): HasMany
    {
        return $this->hasMany(FrameworkQuestionPlacementDomainOverride::class, 'framework_question_placement_id', 'framework_question_placement_id');
    }
}
