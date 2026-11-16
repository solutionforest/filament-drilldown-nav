<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $support = Agent::create(['name' => 'Support bot']);
        $support->documents()->createMany([['title' => 'Refund policy'], ['title' => 'Shipping FAQ']]);

        Agent::create(['name' => 'Sales assistant']);

        User::factory()->create([
            'name' => 'Demo',
            'email' => 'demo@example.com',
        ]);
    }
}
