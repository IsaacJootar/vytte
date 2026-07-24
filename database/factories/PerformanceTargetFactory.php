<?php

namespace Database\Factories;

use App\Models\PerformanceTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceTarget>
 */
class PerformanceTargetFactory extends Factory
{
    protected $model = PerformanceTarget::class;

    public function definition(): array
    {
        return [
            'domain_code' => null,
            'target_score' => fake()->numberBetween(60, 90),
            'created_by' => User::factory(),
        ];
    }
}
