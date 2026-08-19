<?php

namespace Tests\Unit;

use App\Services\Reporting\DiagnosticsService;
use App\Services\Reporting\InsightCatalog;
use App\Services\Reporting\InsightService;
use App\Services\Reporting\RecommendationService;
use App\Services\Reporting\ReportComposer;
use App\Services\Reporting\RiskService;
use App\Services\Reporting\RootCauseService;
use PHPUnit\Framework\TestCase;

class ReportingEngineTest extends TestCase
{
    /**
     * A payload with one weak, one strong, and one unanswered domain.
     *
     * @return array<string, mixed>
     */
    private function payload(string $overallCalibration = 'CALIBRATED'): array
    {
        return [
            'score' => ['overall_score' => 52.0, 'calibration_status' => $overallCalibration],
            'domain_scores' => [
                ['domain_name' => 'Governance', 'domain_code' => 'GOV', 'score' => 22.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 10, 'questions_answered' => 10, 'failed_indicators' => [
                    ['question_id' => 'q1', 'question_text' => 'Is there a governance board?', 'score' => 0.0],
                    ['question_id' => 'q2', 'question_text' => 'Are decisions documented?', 'score' => 20.0],
                ]],
                ['domain_name' => 'Workforce', 'domain_code' => 'WORK', 'score' => 84.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 8, 'questions_answered' => 8],
                ['domain_name' => 'Financing', 'domain_code' => 'FIN', 'score' => null, 'calibration_status' => 'NOT_CALIBRATED', 'questions_expected' => 6, 'questions_answered' => 0],
            ],
        ];
    }

    public function test_diagnostics_classifies_each_domain(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());

        $byDomain = collect($findings)->keyBy('measurement_domain');

        $this->assertSame('WEAKNESS', $byDomain['GOV']['category']);
        $this->assertSame('HIGH', $byDomain['GOV']['severity']); // 22 is below the severe line
        $this->assertSame('STRENGTH', $byDomain['WORK']['category']);
        $this->assertSame('DATA_GAP', $byDomain['FIN']['category']);
    }

    public function test_weakness_carries_failed_indicators_consequence_and_expected_impact(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());
        $gov = collect($findings)->firstWhere('measurement_domain', 'GOV');

        $this->assertCount(2, $gov['failed_indicators']);
        $this->assertNotNull($gov['consequence']);
        $this->assertStringContainsString('left as it is', $gov['consequence']);
        // 22/100 in a high-criticality domain → high improvement potential.
        $this->assertSame('HIGH', $gov['expected_impact']);
    }

    public function test_root_causes_detect_a_failing_cluster(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());
        $causes = (new RootCauseService)->rootCauses($findings);

        $this->assertNotEmpty($causes);
        $this->assertStringContainsString('systemic cause', $causes[0]['statement']);
    }

    public function test_risks_combine_likelihood_and_impact(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());
        $risks = (new RiskService)->risks($findings);

        $gov = collect($risks)->firstWhere('measurement_domain', 'GOV');
        $this->assertNotNull($gov);
        // HIGH severity (likelihood HIGH) x GOV criticality (HIGH impact) => HIGH risk.
        $this->assertSame('HIGH', $gov['level']);
        $this->assertNotNull($gov['consequence']);
    }

    public function test_intelligence_carries_root_causes_and_risks(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $intelligence = $composer->intelligence($this->payload());

        $this->assertArrayHasKey('root_causes', $intelligence);
        $this->assertArrayHasKey('risks', $intelligence);
        $this->assertNotEmpty($intelligence['risks']);
    }

    public function test_custom_report_view_changes_emphasis_without_hiding_critical_findings(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $intelligence = $composer->intelligence($this->payload('CRITICAL_FAILURE'));

        $view = $composer->customView($intelligence, 'STRENGTHS', 'BRIEF', 'WORK');

        $this->assertSame('CUSTOM', $view['lens']);
        $this->assertLessThanOrEqual(3, count($view['lead']));
        $this->assertContains('CRITICAL_FINDING', collect($view['lead'])->pluck('category')->all());
        $this->assertContains('STRENGTH', collect($view['lead'])->pluck('category')->all());
    }

    public function test_worst_news_leads(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());

        // The high-severity weakness must sort ahead of the strength and the data gap.
        $this->assertSame('WEAKNESS', $findings[0]['category']);
        $this->assertSame('HIGH', $findings[0]['severity']);
    }

    public function test_critical_failure_becomes_the_top_finding(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload('CRITICAL_FAILURE'));

        $this->assertSame('CRITICAL_FINDING', $findings[0]['category']);
    }

    public function test_every_recommendation_cites_a_finding(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());
        $recommendations = (new RecommendationService)->recommendations($findings);

        $this->assertNotEmpty($recommendations);
        foreach ($recommendations as $rec) {
            $this->assertArrayHasKey('from_finding', $rec);
            $this->assertNotEmpty($rec['from_finding']['statement']);
            // A recommendation may only come from a problem or an unscored gap, never a
            // strength or a moderate opportunity.
            $this->assertContains($rec['from_finding']['category'], ['CRITICAL_FINDING', 'WEAKNESS', 'DATA_GAP']);
        }
    }

    public function test_unscored_domain_produces_a_data_collection_recommendation(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());
        $recommendations = (new RecommendationService)->recommendations($findings);

        // Financing was unanswered — the report must tell the user to go collect it.
        $dataRec = collect($recommendations)->firstWhere('type', 'Data collection');
        $this->assertNotNull($dataRec, 'An unscored domain must generate a data-collection recommendation.');
        $this->assertSame('DATA_GAP', $dataRec['from_finding']['category']);
        $this->assertSame('IMMEDIATE', $dataRec['horizon']);
        $this->assertStringContainsString('Financing', $dataRec['statement']);
    }

    public function test_strengths_do_not_generate_recommendations(): void
    {
        $findings = [
            ['subject' => 'Workforce', 'measurement_domain' => 'WORK', 'category' => 'STRENGTH', 'severity' => 'POSITIVE', 'score' => 90.0, 'statement' => 's', 'why' => 'w', 'evidence' => []],
        ];

        $this->assertSame([], (new RecommendationService)->recommendations($findings));
    }

    public function test_insights_group_by_meaning_and_produce_a_headline(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());
        $insights = (new InsightService)->insights($findings);

        $this->assertCount(1, $insights['weaknesses']);
        $this->assertCount(1, $insights['strengths']);
        $this->assertCount(1, $insights['data_gaps']);
        $this->assertNotNull($insights['headline']);
    }

    public function test_insights_classify_into_the_governed_categories(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());
        $insights = (new InsightService)->insights($findings);
        $codes = collect($insights['items'])->pluck('category_code')->unique();

        // The weak GOV domain (2 failing items, high-criticality, high severity) surfaces as
        // several governed categories at once.
        $this->assertTrue($codes->contains('WEAKNESS'));
        $this->assertTrue($codes->contains('LOW_PERFORMING'));
        $this->assertTrue($codes->contains('PAIN_POINT'));         // it has failing items
        $this->assertTrue($codes->contains('SYSTEMIC_ISSUE'));     // 2+ failing together
        $this->assertTrue($codes->contains('COMPLIANCE_RISK'));    // GOV → compliance risk
        $this->assertTrue($codes->contains('STRATEGIC_PRIORITY')); // GOV is high-criticality
        // The strong WORK domain surfaces as positive categories.
        $this->assertTrue($codes->contains('HIGH_PERFORMING'));

        // Every category emitted must be a real governed category.
        foreach ($codes as $code) {
            $this->assertTrue(InsightCatalog::exists($code), "Unknown insight category: {$code}");
        }
    }

    public function test_good_practice_only_for_excellent_critical_domains(): void
    {
        $findings = [
            ['subject' => 'Safety', 'measurement_domain' => 'SAFE', 'category' => 'STRENGTH', 'severity' => 'POSITIVE', 'score' => 92.0, 'statement' => 's', 'why' => 'w', 'evidence' => [], 'failed_indicators' => []],
        ];
        $codes = collect((new InsightService)->insights($findings)['items'])->pluck('category_code');

        $this->assertTrue($codes->contains('ACHIEVEMENT'));    // >= 85
        $this->assertTrue($codes->contains('GOOD_PRACTICE'));  // >= 85 in a high-criticality domain
    }

    public function test_risk_lens_leads_with_high_severity_only(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $intelligence = $composer->intelligence($this->payload());

        $risk = $composer->throughLens($intelligence, 'RISK');

        $this->assertSame('RISK', $risk['lens']);
        // Risk leads worst-first: the high-severity weakness sits at the top.
        $this->assertSame('WEAKNESS', $risk['lead'][0]['category']);
        $this->assertSame('HIGH', $risk['lead'][0]['severity']);
    }

    public function test_lenses_reinterpret_by_foregrounding_different_domains(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $payload = [
            'score' => ['overall_score' => 40.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                ['domain_name' => 'Patient Safety', 'domain_code' => 'SAFE', 'score' => 20.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
                ['domain_name' => 'Financing', 'domain_code' => 'FIN', 'score' => 25.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
            ],
        ];
        $intelligence = $composer->intelligence($payload);

        // The Clinical/Quality lens foregrounds SAFE and ignores FIN entirely.
        $quality = $composer->throughLens($intelligence, 'QUALITY');
        $qualityDomains = collect($quality['lead'])->pluck('measurement_domain');
        $this->assertTrue($qualityDomains->contains('SAFE'));
        $this->assertFalse($qualityDomains->contains('FIN'));

        // The Value lens foregrounds FIN and ignores SAFE.
        $value = $composer->throughLens($intelligence, 'EFFICIENCY');
        $valueDomains = collect($value['lead'])->pluck('measurement_domain');
        $this->assertTrue($valueDomains->contains('FIN'));
        $this->assertFalse($valueDomains->contains('SAFE'));
    }

    public function test_unknown_lens_falls_back_to_the_default(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $intelligence = $composer->intelligence($this->payload());

        $view = $composer->throughLens($intelligence, 'NONSENSE');

        $this->assertSame('EXECUTIVE', $view['lens']);
    }

    public function test_operations_lens_leads_with_the_most_concretely_broken_finding(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $payload = [
            'score' => ['overall_score' => 30.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                // Listed first, same severity band as Workforce below, but only one failing item.
                ['domain_name' => 'Service Delivery', 'domain_code' => 'SERV', 'score' => 20.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => [
                    ['question_id' => 'q1', 'question_text' => 'Are triage protocols followed?', 'score' => 0.0],
                ]],
                // Listed second, same severity band, but three failing items — more concretely broken.
                ['domain_name' => 'Workforce', 'domain_code' => 'WORK', 'score' => 20.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => [
                    ['question_id' => 'q2', 'question_text' => 'Is staffing adequate on night shift?', 'score' => 0.0],
                    ['question_id' => 'q3', 'question_text' => 'Is overtime tracked?', 'score' => 10.0],
                    ['question_id' => 'q4', 'question_text' => 'Is there a staff retention plan?', 'score' => 5.0],
                ]],
            ],
        ];
        $intelligence = $composer->intelligence($payload);

        $ops = $composer->throughLens($intelligence, 'OPERATIONS');

        $this->assertSame('WORK', $ops['lead'][0]['measurement_domain']);
    }

    public function test_quality_lens_leads_with_safety_before_service(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $payload = [
            'score' => ['overall_score' => 30.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                // Listed first, identical severity to Patient Safety below.
                ['domain_name' => 'Service Delivery', 'domain_code' => 'SERV', 'score' => 20.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
                ['domain_name' => 'Patient Safety', 'domain_code' => 'SAFE', 'score' => 20.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
            ],
        ];
        $intelligence = $composer->intelligence($payload);

        $quality = $composer->throughLens($intelligence, 'QUALITY');

        // Same severity, same rank — only the safety-first tie-break explains this order.
        $this->assertSame('SAFE', $quality['lead'][0]['measurement_domain']);
    }

    public function test_risk_lens_orders_by_true_risk_level_not_raw_severity(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $payload = [
            'score' => ['overall_score' => 35.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                // Listed first, higher raw severity — but a low-criticality domain, so a MEDIUM actual risk.
                ['domain_name' => 'Community & Patient Experience', 'domain_code' => 'PCOM', 'score' => 20.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
                // Listed second, lower raw severity — but a high-criticality domain, so a HIGH actual risk.
                ['domain_name' => 'Governance', 'domain_code' => 'GOV', 'score' => 40.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
            ],
        ];
        $intelligence = $composer->intelligence($payload);

        $risk = $composer->throughLens($intelligence, 'RISK');

        // Raw severity alone would lead with PCOM; likelihood x impact makes GOV the bigger risk.
        $this->assertSame('GOV', $risk['lead'][0]['measurement_domain']);
    }

    public function test_compliance_lens_leads_with_data_gaps_over_weaknesses(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $payload = [
            'score' => ['overall_score' => 30.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                // Listed first, a severe scored weakness.
                ['domain_name' => 'Governance', 'domain_code' => 'GOV', 'score' => 15.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
                // Listed second, unscored — a documentation/evidence gap, which compliance cares about first.
                ['domain_name' => 'Financing', 'domain_code' => 'FIN', 'score' => null, 'calibration_status' => 'NOT_CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 0],
            ],
        ];
        $intelligence = $composer->intelligence($payload);

        $compliance = $composer->throughLens($intelligence, 'COMPLIANCE');

        $this->assertSame('DATA_GAP', $compliance['lead'][0]['category']);
        $this->assertSame('FIN', $compliance['lead'][0]['measurement_domain']);
    }

    public function test_programme_lens_leads_with_highest_improvement_potential(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $payload = [
            'score' => ['overall_score' => 55.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                // Listed first: moderate score, but a low-criticality domain caps it at MEDIUM potential.
                ['domain_name' => 'Community & Patient Experience', 'domain_code' => 'PCOM', 'score' => 68.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
                // Listed second: more headroom, so HIGH improvement potential despite tying on category and rank.
                ['domain_name' => 'Information Systems', 'domain_code' => 'INFO', 'score' => 50.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
            ],
        ];
        $intelligence = $composer->intelligence($payload);

        $programme = $composer->throughLens($intelligence, 'PROGRAMME_EFFECTIVENESS');

        // Both are OPPORTUNITY findings (identical rank) — only expected impact explains the order.
        $this->assertSame('INFO', $programme['lead'][0]['measurement_domain']);
    }

    public function test_custom_view_brief_detail_hides_reasoning_and_consequence(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $intelligence = $composer->intelligence($this->payload());

        $brief = $composer->customView($intelligence, 'PRIORITIES', 'BRIEF');
        $detailed = $composer->customView($intelligence, 'PRIORITIES', 'DETAILED');

        // customView() itself doesn't strip fields (the blade template renders depth) — but it
        // must still carry the detail level through so the template knows what to hide.
        $this->assertSame('BRIEF', $brief['custom']['detail']);
        $this->assertSame('DETAILED', $detailed['custom']['detail']);
        // The underlying finding data is identical either way — depth is presentational only.
        $this->assertSame($brief['lead'][0]['consequence'] ?? null, $detailed['lead'][0]['consequence'] ?? null);
    }

    public function test_root_causes_detect_a_specific_upstream_downstream_pair_beyond_governance(): void
    {
        $findings = (new DiagnosticsService)->findings([
            'score' => ['overall_score' => 30.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                ['domain_name' => 'Workforce', 'domain_code' => 'WORK', 'score' => 25.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
                ['domain_name' => 'Patient Safety', 'domain_code' => 'SAFE', 'score' => 28.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => []],
            ],
        ]);
        $causes = (new RootCauseService)->rootCauses($findings);

        $upstream = collect($causes)->firstWhere('measurement_domain', 'WORK');
        $this->assertNotNull($upstream, 'A weak workforce domain sitting under a weak safety domain should surface as a probable upstream cause.');
        $this->assertStringContainsString('Workforce', $upstream['statement']);
        $this->assertStringContainsString('Patient Safety', $upstream['statement']);
        $this->assertTrue($upstream['is_upstream']);
    }

    public function test_consequence_cites_the_specific_failing_item_when_available(): void
    {
        $findings = (new DiagnosticsService)->findings([
            'score' => ['overall_score' => 20.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                ['domain_name' => 'Governance', 'domain_code' => 'GOV', 'score' => 20.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => [
                    ['question_id' => 'q1', 'question_text' => 'Is there a governance board?', 'score' => 0.0],
                ]],
            ],
        ]);
        $gov = collect($findings)->firstWhere('measurement_domain', 'GOV');

        $this->assertStringContainsString('left as it is', $gov['consequence']);
        $this->assertStringContainsString('Is there a governance board?', $gov['consequence']);
    }

    public function test_grouped_for_document_never_duplicates_an_insight_across_groups(): void
    {
        // A high-criticality, high-severity weakness with 2 failed indicators surfaces as
        // several insight categories from ONE finding: WEAKNESS, LOW_PERFORMING, PAIN_POINT,
        // SYSTEMIC_ISSUE, a domain-risk category, and STRATEGIC_PRIORITY. This is exactly the
        // shape that made naive iteration over insights() render the same item several times.
        $findings = (new DiagnosticsService)->findings([
            'score' => ['overall_score' => 20.0, 'calibration_status' => 'CALIBRATED'],
            'domain_scores' => [
                ['domain_name' => 'Governance', 'domain_code' => 'GOV', 'score' => 15.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 5, 'questions_answered' => 5, 'failed_indicators' => [
                    ['question_id' => 'q1', 'question_text' => 'Is there a governance board?', 'score' => 0.0],
                    ['question_id' => 'q2', 'question_text' => 'Are decisions documented?', 'score' => 10.0],
                ]],
            ],
        ]);
        $insights = (new InsightService)->insights($findings);
        $groups = (new InsightService)->groupedForDocument($insights);

        // One finding surfacing as 6 categories must produce 6 groups, one item each — never
        // the same item appearing again under 'items', 'negative', 'weaknesses', etc.
        $this->assertGreaterThan(1, count($insights['items']), 'The fixture must exercise a finding with several insight categories.');
        $this->assertSame(collect($insights['items'])->pluck('category_code')->unique()->count(), count($groups));
        $totalRows = collect($groups)->sum(fn ($g) => count($g['rows']));
        $this->assertSame(count($insights['items']), $totalRows, 'Every insight item must appear exactly once across all document groups.');
    }

    public function test_grouped_for_document_returns_nothing_for_an_empty_insights_structure(): void
    {
        $this->assertSame([], (new InsightService)->groupedForDocument([]));
    }

    public function test_recommendations_are_contextual_and_cite_evidence(): void
    {
        $findings = (new DiagnosticsService)->findings($this->payload());
        $recs = (new RecommendationService)->recommendations($findings);

        $gov = collect($recs)->firstWhere('measurement_domain', 'GOV');
        $this->assertNotNull($gov);
        // A specific governance intervention, not "strengthen governance".
        $this->assertStringContainsString('accountab', strtolower($gov['statement']));
        // It aims at the concrete failing items and carries expected impact.
        $this->assertStringContainsString('governance board', strtolower($gov['statement']));
        $this->assertSame('HIGH', $gov['expected_impact']);
        $this->assertNotEmpty($gov['focus_items']);
    }

    public function test_intelligence_is_deterministic(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);

        $a = $composer->intelligence($this->payload());
        $b = $composer->intelligence($this->payload());

        // generated_at aside, the derived intelligence must be identical run to run.
        unset($a['generated_at'], $b['generated_at']);
        $this->assertSame($a, $b);
    }
}
