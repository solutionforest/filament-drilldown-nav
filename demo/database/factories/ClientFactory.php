<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'industry' => fake()->randomElement(['Retail', 'Finance', 'Healthcare', 'Education', 'Logistics', 'Hospitality', 'Media']),
            'status' => fake()->randomElement(['active', 'active', 'active', 'prospect', 'churned']),
        ];
    }
}
