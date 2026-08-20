<?php

namespace Tests\Feature;

use App\Models\DepartmentFrameworkVersion;
use App\Models\ScoringModelVersion;
use App\Services\AssessmentBuilderService;
use App\Services\AssessmentVersionService;
use App\Services\FrameworkContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionedScoringModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_published_framework_has_an_immutable_scoring_model(): void
    {
        $published = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)->get();

        $this->assertNotEmpty($published);
        foreach ($published as $framework) {
            $model = $framework->scoringModelVersion;
            $this->assertNotNull($model);
            $this->assertSame(ScoringModelVersion::STATUS_PUBLISHED, $model->status);
            $this->assertNotNull($model->content_hash);
            $this->assertSame($framework->questionPlacements()->count(), $model->itemRules()->count());
        }
    }

    public function test_a_published_question_can_have_a_new_instrument_score_without_changing_its_semantics(): void
    {
        $published = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->whereHas('questionPlacements.questionVersion', fn ($query) => $query->whereNotNull('options'))
            ->firstOrFail();
        $draft = app(AssessmentVersionService::class)->cloneToDraft($published);
        $placement = $draft->questionPlacements()
            ->with('questionVersion.questionType')
            ->whereNotNull('sub_index_id')
            ->whereHas('questionVersion', fn ($query) => $query->whereNotNull('options'))
            ->firstOrFail();
        $questionVersion = $placement->questionVersion;
        $before = $questionVersion->options;
        $points = collect($before)->mapWithKeys(fn ($option) => [(int) $option['option_order'] => 25])->all();

        app(AssessmentBuilderService::class)->applyScoringAndEvidence($placement, [
            'is_scored' => true,
            'scoring_group_id' => $placement->sub_index_id,
            'importance' => 'normal',
            'evidence_mode' => 'none',
            'points' => $points,
            'critical' => [],
        ]);

        $this->assertEquals($before, $questionVersion->fresh()->options, 'Published question semantics were changed by an instrument scoring choice.');
        $rule = $placement->fresh()->scoringItemRule;
        $this->assertNotNull($rule);
        $this->assertSame('OPTION_MAP', $rule->method);
        $this->assertTrue(collect($rule->rule_config['option_scores'])->every(fn ($option) => (float) $option['score'] === 25.0));

        $payload = app(FrameworkContentService::class)->frameworkPayload($draft->fresh());
        $rendered = collect($payload['questions'])->firstWhere('framework_question_placement_id', $placement->framework_question_placement_id);
        $this->assertTrue(collect($rendered['options'])->every(fn ($option) => (float) $option['score_weight'] === 25.0));
        $this->assertSame($rule->scoring_model_version_id, $payload['scoring_model']['scoring_model_version_id']);
    }

    /**
     * cloneToDraft() previously copied sections, indicators, and placements but never
     * FrameworkIndicatorDomainMapping rows — so every framework version bump silently
     * dropped domain-scoring routing for its indicators, even though the indicator itself
     * (and its questions) carried over untouched. A real WASH assessment surfaced this: two
     * indicators lost their domain mapping across separate clones, so answered questions
     * became invisible to domain scoring with no error or warning anywhere.
     */
    public function test_cloning_a_framework_version_carries_forward_its_indicator_domain_mappings(): void
    {
        $published = DepartmentFrameworkVersion::where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->whereHas('indicators.domainMappings')
            ->firstOrFail();
        $originalMappings = $published->indicators->flatMap->domainMappings;
        $this->assertNotEmpty($originalMappings, 'Fixture must have at least one indicator domain mapping to prove it survives cloning.');

        $draft = app(AssessmentVersionService::class)->cloneToDraft($published);
        $draft->load('indicators.domainMappings');
        $clonedMappings = $draft->indicators->flatMap->domainMappings;

        $this->assertSame($originalMappings->count(), $clonedMappings->count(), 'Every indicator domain mapping must survive the clone.');
        $this->assertSame(
            $originalMappings->pluck('domain_definition_id')->sort()->values()->all(),
            $clonedMappings->pluck('domain_definition_id')->sort()->values()->all(),
            'The cloned mappings must point at the same domains as the original.'
        );
    }

    public function test_published_scoring_models_and_rules_are_immutable(): void
    {
        $model = ScoringModelVersion::where('status', ScoringModelVersion::STATUS_PUBLISHED)->firstOrFail();

        $this->expectException(\LogicException::class);
        $model->update(['construct' => 'NEED']);
    }
}
