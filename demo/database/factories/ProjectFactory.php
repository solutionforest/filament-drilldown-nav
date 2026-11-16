<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $kind = fake()->randomElement(['Website redesign', 'Mobile app', 'Brand refresh', 'Annual report', 'Campaign', 'SEO audit', 'Intranet', 'Onboarding flow']);

        return [
            'name' => $kind.' '.fake()->randomElement(['2026', 'Q1', 'Q2', 'Phase 2', 'MVP', 'Relaunch']),
            'status' => fake()->randomElement(['active', 'active', 'on hold', 'completed']),
            'due_on' => fake()->dateTimeBetween('-1 month', '+8 months'),
            'budget' => fake()->numberBetween(5, 120) * 1000,
        ];
    }
}
