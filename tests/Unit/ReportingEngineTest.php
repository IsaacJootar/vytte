<?php

namespace Tests\Unit;

use App\Services\Reporting\DiagnosticsService;
use App\Services\Reporting\InsightService;
use App\Services\Reporting\RecommendationService;
use App\Services\Reporting\ReportComposer;
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
                ['domain_name' => 'Governance', 'domain_code' => 'GOV', 'score' => 22.0, 'calibration_status' => 'CALIBRATED', 'questions_expected' => 10, 'questions_answered' => 10],
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
            // A recommendation may only come from a problem, never a strength or a gap.
            $this->assertContains($rec['from_finding']['category'], ['CRITICAL_FINDING', 'WEAKNESS']);
        }
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

    public function test_risk_lens_leads_with_high_severity_only(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService);
        $intelligence = $composer->intelligence($this->payload());

        $risk = $composer->throughLens($intelligence, 'RISK');

        $this->assertSame('RISK', $risk['lens']);
        foreach ($risk['lead'] as $finding) {
            $this->assertSame('HIGH', $finding['severity']);
        }
    }

    public function test_unknown_lens_falls_back_to_performance(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService);
        $intelligence = $composer->intelligence($this->payload());

        $view = $composer->throughLens($intelligence, 'NONSENSE');

        $this->assertSame('PERFORMANCE', $view['lens']);
    }

    public function test_intelligence_is_deterministic(): void
    {
        $composer = new ReportComposer(new DiagnosticsService, new InsightService, new RecommendationService);

        $a = $composer->intelligence($this->payload());
        $b = $composer->intelligence($this->payload());

        // generated_at aside, the derived intelligence must be identical run to run.
        unset($a['generated_at'], $b['generated_at']);
        $this->assertSame($a, $b);
    }
}
