<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * A believable agency: 12 clients, ~60 projects, ~800 tasks, ~300 documents,
 * ~150 invoices and ~1,500 time entries, plus one user per role.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        mt_srand(2026);
        fake()->seed(2026);

        $users = [
            User::factory()->create(['name' => 'Demo Admin', 'email' => 'demo@example.com', 'role' => 'admin']),
            User::factory()->create(['name' => 'Mia Manager', 'email' => 'manager@example.com', 'role' => 'manager']),
            User::factory()->create(['name' => 'Sam Member', 'email' => 'member@example.com', 'role' => 'member']),
            User::factory()->create(['name' => 'Noor Member', 'email' => 'noor@example.com', 'role' => 'member']),
        ];

        // The first client is the one our "client" login belongs to; keep its data recognisable.
        $acme = Client::factory()->create(['name' => 'Acme Corp', 'industry' => 'Retail', 'status' => 'active']);
        $clients = Client::factory()->count(11)->create()->prepend($acme);

        User::factory()->create(['name' => 'Casey (Acme)', 'email' => 'client@example.com', 'role' => 'client', 'client_id' => $acme->id]);

        foreach ($clients as $client) {
            Project::factory()->count($client->is($acme) ? 6 : fake()->numberBetween(3, 6))->create(['client_id' => $client->id])
                ->each(function (Project $project) use ($users): void {
                    Task::factory()->count(fake()->numberBetween(8, 20))->create([
                        'project_id' => $project->id,
                        'assignee_id' => fn () => fake()->randomElement($users)->id, // per task, not per batch
                    ]);
                    TimeEntry::factory()->count(fake()->numberBetween(15, 35))->create([
                        'project_id' => $project->id,
                        'user_id' => fn () => fake()->randomElement($users)->id,
                    ]);
                    foreach (fake()->randomElements(['Brief', 'Contract', 'Style guide', 'Sitemap', 'Meeting notes', 'Wireframes', 'Invoice backup'], fake()->numberBetween(2, 6)) as $title) {
                        Document::create(['project_id' => $project->id, 'title' => $title.' – '.$project->name]);
                    }
                });

            Invoice::factory()->count(fake()->numberBetween(8, 16))->create(['client_id' => $client->id]);
        }
    }
}
