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
            ['issue_key' => 'persistent', 'question_text' => 'Still failing'],
            ['issue_key' => 'new', 'question_text' => 'New issue'],
        ]];
        $previous = ['issue_register' => [
            ['issue_key' => 'persistent', 'question_text' => 'Still failing'],
            ['issue_key' => 'resolved', 'question_text' => 'Was fixed'],
        ]];

        $result = $service->compare($current, $previous);

        $this->assertSame(['new' => 1, 'persistent' => 1, 'resolved' => 1], $result['counts']);
        $this->assertSame('PERSISTENT', collect($result['open'])->firstWhere('issue_key', 'persistent')['progress_status']);
        $this->assertSame('NEW', collect($result['open'])->firstWhere('issue_key', 'new')['progress_status']);
        $this->assertSame('RESOLVED', $result['resolved'][0]['progress_status']);
    }
}
