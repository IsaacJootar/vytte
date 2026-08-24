<?php

namespace Tests\Unit;

use App\Services\AssessmentLogicService;
use PHPUnit\Framework\TestCase;

class AssessmentLogicServiceTest extends TestCase
{
    private function service(): AssessmentLogicService
    {
        return new AssessmentLogicService;
    }

    private function rule(string $operator, array $conditions): array
    {
        return ['version' => 1, 'type' => 'response_rule', 'operator' => $operator, 'conditions' => $conditions];
    }

    public function test_all_operator_requires_every_condition(): void
    {
        $rule = $this->rule('ALL', [
            ['source_question_id' => 'q1', 'comparison' => 'OPTION_SELECTED', 'value' => 1],
            ['source_question_id' => 'q2', 'comparison' => 'OPTION_SELECTED', 'value' => 2],
        ]);

        $bothMatch = ['q1' => ['option_ids' => [1]], 'q2' => ['option_ids' => [2]]];
        $onlyOneMatches = ['q1' => ['option_ids' => [1]], 'q2' => ['option_ids' => [9]]];

        $this->assertTrue($this->service()->matches($rule, $bothMatch));
        $this->assertFalse($this->service()->matches($rule, $onlyOneMatches));
    }

    public function test_any_operator_requires_only_one_condition(): void
    {
        $rule = $this->rule('ANY', [
            ['source_question_id' => 'q1', 'comparison' => 'OPTION_SELECTED', 'value' => 1],
            ['source_question_id' => 'q2', 'comparison' => 'OPTION_SELECTED', 'value' => 2],
        ]);

        $onlyOneMatches = ['q1' => ['option_ids' => [1]], 'q2' => ['option_ids' => [9]]];
        $neitherMatches = ['q1' => ['option_ids' => [9]], 'q2' => ['option_ids' => [9]]];

        $this->assertTrue($this->service()->matches($rule, $onlyOneMatches));
        $this->assertFalse($this->service()->matches($rule, $neitherMatches));
    }

    public function test_gating_fails_closed_on_a_malformed_rule(): void
    {
        $service = $this->service();

        $this->assertFalse($service->matches(null, ['q1' => ['option_ids' => [1]]]));
        $this->assertFalse($service->matches(['type' => 'something_else'], []));
        $this->assertFalse($service->matches(['version' => 1, 'type' => 'response_rule', 'operator' => 'ALL', 'conditions' => []], []));
    }

    public function test_visibility_fails_open_on_a_malformed_rule_unlike_gating(): void
    {
        // isVisible() and matches() share a rule shape but must not share a default: showing
        // an extra question by mistake costs nothing, fabricating a critical failure does not.
        $this->assertTrue($this->service()->isVisible(null, []));
        $this->assertFalse($this->service()->matches(null, []));
    }

    public function test_gating_reads_facts_built_from_responses(): void
    {
        $response = (object) [
            'question_id' => 'q1',
            'response_state' => 'ANSWERED',
            'typed_value' => ['option_ids' => []],
            'value_option_id' => 3,
            'value_numeric' => null,
            'value_text' => null,
        ];
        $facts = $this->service()->factsFromResponses(collect([$response]));

        $rule = $this->rule('ALL', [
            ['source_question_id' => 'q1', 'comparison' => 'OPTION_SELECTED', 'value' => 3],
        ]);

        $this->assertTrue($this->service()->matches($rule, $facts));
    }
}
