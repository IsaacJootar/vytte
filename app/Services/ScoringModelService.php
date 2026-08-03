<?php

namespace App\Services;

use App\Models\DepartmentFrameworkVersion;
use App\Models\ScoringItemRule;
use App\Models\ScoringModel;
use App\Models\ScoringModelVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScoringModelService
{
    public function ensureForFramework(DepartmentFrameworkVersion $framework): ScoringModelVersion
    {
        if ($framework->scoring_model_version_id) {
            return ScoringModelVersion::with('itemRules')->findOrFail($framework->scoring_model_version_id);
        }

        return DB::transaction(function () use ($framework): ScoringModelVersion {
            $framework = DepartmentFrameworkVersion::whereKey($framework->framework_version_id)->lockForUpdate()->firstOrFail();
            if ($framework->scoring_model_version_id) {
                return ScoringModelVersion::with('itemRules')->findOrFail($framework->scoring_model_version_id);
            }

            $model = ScoringModel::create([
                'content_publisher_id' => $framework->content_publisher_id,
                'model_code' => 'SM-'.strtoupper(Str::random(12)),
                'name' => $framework->display_name.' scoring model',
                'description' => 'Versioned scoring interpretation for '.$framework->display_name.'.',
            ]);
            $version = ScoringModelVersion::create([
                'scoring_model_id' => $model->scoring_model_id,
                'framework_version_id' => $framework->framework_version_id,
                'version_number' => 1,
                'status' => ScoringModelVersion::STATUS_DRAFT,
                'score_purpose' => 'PRIMARY',
                'construct' => 'READINESS',
                'direction' => 'HIGHER_IS_BETTER',
                'algorithm_version' => ScoringService::ALGORITHM_VERSION,
                'missing_policy' => $this->defaultMissingPolicy(),
                'aggregation_config' => ['method' => 'MEAN_OF_SCORED_SUB_INDICES'],
                'published_at' => $framework->published_at,
                'published_by' => $framework->published_by,
            ]);

            DB::table('department_framework_versions')->where('framework_version_id', $framework->framework_version_id)->update([
                'scoring_model_version_id' => $version->scoring_model_version_id,
            ]);
            $framework->scoring_model_version_id = $version->scoring_model_version_id;

            $this->syncRulesFromLegacy($framework, $version);
            if ($framework->status !== DepartmentFrameworkVersion::STATUS_DRAFT) {
                $version->update([
                    'status' => ScoringModelVersion::STATUS_PUBLISHED,
                    'content_hash' => $this->hash($version->fresh('itemRules')),
                ]);
            }

            return $version->fresh('itemRules');
        });
    }

    public function backfillLegacyModels(): void
    {
        DepartmentFrameworkVersion::query()->orderBy('created_at')->each(fn ($framework) => $this->ensureForFramework($framework));
    }

    public function syncRuleFromPlacement(DepartmentFrameworkVersion $framework, $placement, ?array $ruleConfig = null): ScoringItemRule
    {
        $version = $this->ensureForFramework($framework);
        $questionVersion = $placement->questionVersion;
        $type = $questionVersion?->questionType?->type_code;

        return ScoringItemRule::updateOrCreate(
            [
                'scoring_model_version_id' => $version->scoring_model_version_id,
                'framework_question_placement_id' => $placement->framework_question_placement_id,
            ],
            [
                'question_version_id' => $placement->question_version_id,
                'sub_index_id' => $placement->sub_index_id,
                'method' => ! $placement->scoring_contribution ? 'UNSCORED' : ($type === 'NUMERIC' ? 'NUMERIC_BANDS' : 'OPTION_MAP'),
                'score_role' => 'PRIMARY',
                'weight' => $placement->weight,
                'criticality' => $placement->criticality,
                'rule_config' => $ruleConfig ?? [
                    'option_scores' => collect($questionVersion?->options ?? [])->map(fn ($option) => [
                        'option_id' => $option['option_id'] ?? null,
                        'option_order' => $option['option_order'] ?? null,
                        'score' => $option['score_weight'] ?? null,
                        'critical_failure' => (bool) ($option['critical_failure'] ?? false),
                    ])->values()->all(),
                    'numeric_bands' => collect($questionVersion?->numeric_bands ?? [])->values()->all(),
                ],
            ],
        );
    }

    public function publishForFramework(DepartmentFrameworkVersion $framework, ?string $userId): ScoringModelVersion
    {
        $version = $this->ensureForFramework($framework);
        foreach ($framework->questionPlacements()->with('questionVersion.questionType')->get() as $placement) {
            $this->syncRuleFromPlacement($framework, $placement);
        }
        $hash = $this->hash($version->fresh('itemRules'));
        $version->update([
            'status' => ScoringModelVersion::STATUS_PUBLISHED,
            'content_hash' => $hash,
            'published_at' => now(),
            'published_by' => $userId,
        ]);

        return $version->fresh('itemRules');
    }

    public function comparisonSignature(array $parts): string
    {
        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    private function syncRulesFromLegacy(DepartmentFrameworkVersion $framework, ScoringModelVersion $version): void
    {
        foreach ($framework->questionPlacements()->with('questionVersion.questionType')->get() as $placement) {
            $this->syncRuleFromPlacement($framework, $placement);
        }
    }

    private function hash(ScoringModelVersion $version): string
    {
        return hash('sha256', json_encode([
            'scoring_model_version_id' => $version->scoring_model_version_id,
            'version_number' => $version->version_number,
            'score_purpose' => $version->score_purpose,
            'construct' => $version->construct,
            'direction' => $version->direction,
            'algorithm_version' => $version->algorithm_version,
            'missing_policy' => $version->missing_policy,
            'aggregation_config' => $version->aggregation_config,
            'item_rules' => $version->itemRules->sortBy('framework_question_placement_id')->map->only([
                'framework_question_placement_id', 'question_version_id', 'sub_index_id', 'method', 'score_role', 'weight', 'criticality', 'rule_config',
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    private function defaultMissingPolicy(): array
    {
        return [
            'MISSING' => 'EXCLUDE_AND_MARK_PARTIAL',
            'NOT_APPLICABLE' => 'EXCLUDE_FROM_DENOMINATOR',
            'UNKNOWN' => 'EXCLUDE_AND_MARK_PARTIAL',
            'NOT_ASSESSED' => 'EXCLUDE_AND_MARK_PARTIAL',
            'NOT_OBSERVED' => 'EXCLUDE_AND_MARK_PARTIAL',
            'DECLINED' => 'EXCLUDE_AND_MARK_PARTIAL',
        ];
    }
}
