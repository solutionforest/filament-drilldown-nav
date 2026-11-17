<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Tasks\TaskResource;
use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The agency demo (thousands of rows, roles, real badges, five menu levels), served by the real panel. */
class RealWorldDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Visible text of the drill-down sidebar only (whitespace collapsed). */
    private function sidebar(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', (string) $response->getContent(), $m), 'drill-down sidebar not rendered exactly once');

        return trim((string) preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<(?:p class="fi-drilldown-empty"|div class="fi-drilldown-shortcuts").*$#s', '', $m[1]))));
    }

    private function as(string $email, string $url): TestResponse
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail())->get($url);
    }

    public function test_the_seeded_agency_is_big_enough_to_feel_real(): void
    {
        $this->assertSame(12, Client::count());
        $this->assertGreaterThan(40, Project::count());
        $this->assertGreaterThan(500, Task::count());
        $this->assertGreaterThan(150, Document::count());
        $this->assertGreaterThan(100, Invoice::count());
        $this->assertGreaterThan(1000, TimeEntry::count());
        $this->assertSame(['admin', 'client', 'manager', 'member'], User::query()->distinct()->orderBy('role')->pluck('role')->all());
    }

    public function test_every_list_and_table_page_renders_with_real_rows(): void
    {
        // Pick what each table shows FIRST (its default sort), so the row is on page one.
        $client = Client::orderBy('name')->firstOrFail();
        $acme = Client::where('name', 'Acme Corp')->firstOrFail();
        $project = $acme->projects()->orderBy('due_on')->firstOrFail();

        foreach ([
            '/admin/clients' => $client->name,
            "/admin/clients/{$acme->id}/projects" => $project->name,
            "/admin/clients/{$acme->id}/invoices" => $acme->invoices()->orderByDesc('due_on')->first()->number,
            '/admin/projects' => Project::orderBy('due_on')->first()->name,
            "/admin/projects/{$project->id}/tasks" => $project->tasks()->orderBy('due_on')->first()->title,
            "/admin/projects/{$project->id}/time" => 'Total',
            "/admin/projects/{$project->id}/documents" => $project->documents()->orderBy('id')->first()->title,
        ] as $url => $expected) {
            $this->as('demo@example.com', $url)->assertOk()->assertSee(e($expected), false);
        }
    }

    public function test_client_menu_has_real_counts_in_its_badges(): void
    {
        $client = Client::where('name', 'Acme Corp')->firstOrFail();
        $unpaid = $client->invoices()->whereIn('status', ['sent', 'overdue'])->count();

        $text = $this->sidebar($this->as('demo@example.com', "/admin/clients/{$client->id}"));

        $this->assertMatchesRegularExpression('/^Back Acme Corp Overview Projects '.$client->projects()->count().' Invoices '.$unpaid.' Contacts$/', $text);
    }

    public function test_a_clients_projects_open_as_their_own_level(): void
    {
        $client = Client::where('name', 'Acme Corp')->firstOrFail();

        $text = $this->sidebar($this->as('demo@example.com', "/admin/clients/{$client->id}/projects"));

        $this->assertStringStartsWith('Back Projects ', $text);
        foreach ($client->projects()->latest('due_on')->limit(3)->pluck('name') as $name) {
            $this->assertStringContainsString($name, $text);
        }
    }

    public function test_five_levels_projects_project_tasks_task_comments(): void
    {
        $project = Client::where('name', 'Acme Corp')->firstOrFail()->projects()->firstOrFail();
        $task = $project->tasks()->firstOrFail();
        $open = $project->tasks()->where('status', '!=', 'done')->count();

        // level 2: the project's menu, with the real open-task count on Tasks
        $level2 = $this->sidebar($this->as('demo@example.com', "/admin/projects/{$project->id}/tasks"));
        $this->assertMatchesRegularExpression('/^Back '.preg_quote($project->name, '/').' Overview Tasks '.$open.' Time Files Settings$/', $level2);

        // level 4: one task, with its own menu under a custom header (recordHeader) instead of Back + title
        $status = (string) str($task->status)->headline();

        foreach (['edit' => 'Details', 'comments' => 'Comments', 'history' => 'History'] as $page => $label) {
            $response = $this->as('demo@example.com', "/admin/projects/{$project->id}/tasks/{$task->id}/{$page}");
            $response->assertOk();
            $this->assertSame('All tasks '.$task->title.' '.$status.' Details Comments History', $this->sidebar($response), $page);
            $response->assertSee('class="fi-drilldown-custom-header" tabindex="-1" x-ref="title"', false);
            $response->assertSee('href="'.e(TaskResource::getIndexUrl(['project' => $project])).'"', false);
            $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>.*?'.$label.'#s', (string) $response->getContent());
        }
    }

    public function test_client_login_sees_only_its_own_clients_projects_and_no_staff_items(): void
    {
        $own = Client::where('name', 'Acme Corp')->firstOrFail()->projects()->firstOrFail();
        $foreign = Client::where('name', '!=', 'Acme Corp')->firstOrFail()->projects()->firstOrFail();

        $root = $this->sidebar($this->as('client@example.com', '/admin'));
        $this->assertStringContainsString('Projects', $root);
        $this->assertStringNotContainsString('Clients', $root);
        $this->assertStringNotContainsString('Audit log', $root);

        $menu = $this->sidebar($this->as('client@example.com', "/admin/projects/{$own->id}"));
        $this->assertStringContainsString('Overview', $menu);
        $this->assertStringContainsString('Tasks', $menu);
        $this->assertStringNotContainsString('Time', $menu);
        $this->assertStringNotContainsString('Settings', $menu);

        $this->as('client@example.com', "/admin/projects/{$foreign->id}")->assertNotFound();
        $this->as('client@example.com', "/admin/projects/{$own->id}/time")->assertForbidden();
        $this->as('client@example.com', '/admin/clients')->assertForbidden();
    }

    public function test_each_role_gets_its_own_top_level_menu(): void
    {
        $menus = [];
        foreach (['demo@example.com', 'manager@example.com', 'member@example.com', 'client@example.com'] as $email) {
            $menus[$email] = $this->sidebar($this->as($email, '/admin'));
        }

        $this->assertStringContainsString('Audit log', $menus['demo@example.com']);
        $this->assertStringContainsString('Clients', $menus['demo@example.com']);
        $this->assertStringNotContainsString('Audit log', $menus['manager@example.com']);
        $this->assertStringContainsString('Clients', $menus['manager@example.com']);
        $this->assertStringNotContainsString('Clients', $menus['member@example.com']);
        $this->assertStringNotContainsString('Clients', $menus['client@example.com']);
    }

    public function test_the_whole_demo_stays_within_a_sane_query_budget(): void
    {
        $project = Project::firstOrFail();
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        \DB::enableQueryLog();
        $this->actingAs($user)->get("/admin/projects/{$project->id}/tasks")->assertOk();
        $queries = count(\DB::getQueryLog());

        // The sidebar must not scale with data: a page of a 700-task database stays far below N+1 territory.
        $this->assertLessThan(60, $queries, "{$queries} queries for one page");
    }
}
