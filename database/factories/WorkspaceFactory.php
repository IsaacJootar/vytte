<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'workspace_type' => 'INDIVIDUAL',
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'plan' => 'FREE',
            'status' => 'ACTIVE',
        ];
    }
}
