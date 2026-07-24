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
        foreach ($risk['lead'] as $finding) {
            $this->assertSame('HIGH', $finding['severity']);
        }
    }

    public function test_unknown_lens_falls_back_to_performance(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService, new RootCauseService, new RiskService);
        $intelligence = $composer->intelligence($this->payload());

        $view = $composer->throughLens($intelligence, 'NONSENSE');

        $this->assertSame('PERFORMANCE', $view['lens']);
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
