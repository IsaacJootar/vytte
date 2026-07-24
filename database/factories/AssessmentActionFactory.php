<?php

namespace Database\Factories;

use App\Models\AssessmentAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAction>
 */
class AssessmentActionFactory extends Factory
{
    protected $model = AssessmentAction::class;

    public function definition(): array
    {
        $statement = 'Strengthen '.fake()->words(2, true).'.';

        return [
            'source_finding_category' => 'WEAKNESS',
            'source_finding_subject' => fake()->words(2, true),
            'source_finding_statement' => $statement,
            'source_measurement_domain' => fake()->randomElement(['GOV', 'WORK', 'SERV', 'FIN']),
            'recommendation_statement' => $statement,
            'title' => $statement,
            'priority' => fake()->randomElement(AssessmentAction::PRIORITIES),
            'status' => AssessmentAction::STATUS_OPEN,
            'created_by' => User::factory(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => AssessmentAction::STATUS_VERIFIED,
            'verified_by' => User::factory(),
            'verified_at' => now(),
        ]);
    }
}
