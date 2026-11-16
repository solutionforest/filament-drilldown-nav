<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'INV-'.fake()->unique()->numerify('2026-####'),
            'amount' => fake()->numberBetween(8, 90) * 100,
            'status' => fake()->randomElement(['paid', 'paid', 'paid', 'sent', 'sent', 'overdue', 'draft']),
            'due_on' => fake()->dateTimeBetween('-3 months', '+2 months'),
        ];
    }
}
