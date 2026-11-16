<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Design', 'Review', 'Implement', 'Test', 'Write copy for', 'Prepare', 'Fix', 'Deploy']).' '.fake()->randomElement(['homepage', 'checkout', 'navigation', 'newsletter', 'landing page', 'dashboard', 'contact form', 'pricing page', 'analytics', 'style guide']),
            'status' => fake()->randomElement(['todo', 'todo', 'doing', 'doing', 'review', 'done', 'done', 'done']),
            'priority' => fake()->randomElement(['low', 'normal', 'normal', 'high']),
            'due_on' => fake()->dateTimeBetween('-2 weeks', '+3 months'),
        ];
    }
}
