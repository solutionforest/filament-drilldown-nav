<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $support = Project::create(['name' => 'Website redesign']);
        $support->documents()->createMany([['title' => 'Refund policy'], ['title' => 'Shipping FAQ']]);

        Project::create(['name' => 'Mobile app']);
        Project::create(['name' => 'Annual report']);

        User::factory()->create([
            'name' => 'Demo',
            'email' => 'demo@example.com',
        ]);
    }
}
