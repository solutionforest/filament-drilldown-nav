<?php

namespace Database\Factories;

use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TimeEntry> */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'minutes' => fake()->randomElement([30, 45, 60, 90, 120, 180, 240]),
            'worked_on' => fake()->dateTimeBetween('-3 months', 'now'),
            'note' => fake()->randomElement(['Client call', 'Design review', 'Development', 'QA', 'Planning', null]),
        ];
    }
}
