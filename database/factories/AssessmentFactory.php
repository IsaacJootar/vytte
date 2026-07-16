<?php

namespace Database\Factories;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'status' => 'IN_PROGRESS',
            'publish_status' => 'DRAFT',
            'scope_type' => 'FULL',
            'assessor_name' => fake()->name(),
            'started_at' => now(),
        ];
    }
}
