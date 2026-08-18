<?php

namespace Tests\Unit;

use App\Services\Reporting\IssueTrackingService;
use PHPUnit\Framework\TestCase;

class IssueTrackingServiceTest extends TestCase
{
    public function test_issues_are_classified_as_new_persistent_or_resolved_by_stable_key(): void
    {
        $service = new IssueTrackingService;
        $current = ['issue_register' => [
            ['issue_key' => 'persistent', 'question_text' => 'Still failing', 'item_score' => 20],
            ['issue_key' => 'new', 'question_text' => 'New issue', 'item_score' => 10],
        ]];
        $previous = ['issue_register' => [
            ['issue_key' => 'persistent', 'question_text' => 'Still failing', 'item_score' => 20],
            ['issue_key' => 'resolved', 'question_text' => 'Was fixed', 'item_score' => 15],
        ]];

        $result = $service->compare($current, $previous);

        $this->assertSame(['new' => 1, 'persistent' => 1, 'improving' => 0, 'not_comparable' => 0, 'resolved' => 1], $result['counts']);
        $this->assertSame('PERSISTENT', collect($result['open'])->firstWhere('issue_key', 'persistent')['progress_status']);
        $this->assertSame('NEW', collect($result['open'])->firstWhere('issue_key', 'new')['progress_status']);
        $this->assertSame('RESOLVED', $result['resolved'][0]['progress_status']);
    }

    public function test_an_issue_still_failing_but_scoring_higher_than_before_is_improving(): void
    {
        $service = new IssueTrackingService;
        $current = ['issue_register' => [
            ['issue_key' => 'a', 'question_text' => 'Weak but better', 'item_score' => 35],
        ]];
        $previous = ['issue_register' => [
            ['issue_key' => 'a', 'question_text' => 'Weak but better', 'item_score' => 10],
        ]];

        $result = $service->compare($current, $previous);

        $this->assertSame('IMPROVING', $result['open'][0]['progress_status']);
        $this->assertSame(1, $result['counts']['improving']);
        $this->assertSame(0, $result['counts']['persistent']);
    }

    public function test_issues_are_not_comparable_when_the_series_is_incompatible(): void
    {
        $service = new IssueTrackingService;
        $current = ['issue_register' => [
            ['issue_key' => 'a', 'question_text' => 'Currently failing', 'item_score' => 10],
        ]];
        $previous = ['issue_register' => [
            ['issue_key' => 'a', 'question_text' => 'Currently failing', 'item_score' => 90],
        ]];

        $result = $service->compare($current, $previous, seriesComparable: false);

        $this->assertSame('NOT_COMPARABLE', $result['open'][0]['progress_status']);
        $this->assertSame(1, $result['counts']['not_comparable']);
        // Vytte must not infer that a question absent from an incompatible prior run was
        // resolved — nothing is reported as resolved when the series itself is not comparable.
        $this->assertEmpty($result['resolved']);
        $this->assertSame(0, $result['counts']['resolved']);
    }
}
