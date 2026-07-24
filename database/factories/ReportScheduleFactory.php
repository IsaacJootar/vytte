<?php

namespace Database\Factories;

use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    public function definition(): array
    {
        return [
            'recipient_email' => fake()->safeEmail(),
            'frequency' => fake()->randomElement(ReportSchedule::FREQUENCIES),
            'next_run_at' => now()->addMonth(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => ['next_run_at' => now()->subDay()]);
    }
}
