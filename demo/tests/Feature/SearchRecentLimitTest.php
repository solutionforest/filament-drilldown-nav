<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Menu search, recent/pinned shortcuts, and shortened long lists. */
class SearchRecentLimitTest extends TestCase
{
    use RefreshDatabase;

    private function visit(string $url, string $email = 'demo@example.com'): TestResponse
    {
        $user = User::where('email', $email)->first() ?? User::factory()->create(['email' => $email, 'role' => 'admin']);

        return $this->actingAs($user)->get($url)->assertOk();
    }

    private function nav(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', (string) $response->getContent(), $m));

        return $m[1];
    }

    public function test_search_box_appears_with_its_label_and_every_item_can_be_filtered(): void
    {
        $nav = $this->nav($this->visit('/admin'));

        $this->assertStringContainsString('type="search"', $nav);
        $this->assertStringContainsString('x-model="query"', $nav);
        $this->assertStringContainsString('placeholder="Search menu…"', $nav);
        // items carry their (lower-cased) label for the client-side filter
        $this->assertMatchesRegularExpression("#x-show=\"matches\\('projects'#", $nav);
        $this->assertMatchesRegularExpression("#x-show=\"matches\\('team', '[^']*members#", $nav); // parents match on their children too
    }

    public function test_headings_and_dividers_hide_while_searching_and_there_is_a_no_match_note(): void
    {
        $nav = $this->nav($this->visit('/admin'));

        $this->assertMatchesRegularExpression('#class="fi-drilldown-heading"[^>]*x-show="[^"]*! searching"#', $nav);
        $this->assertMatchesRegularExpression('#class="fi-drilldown-divider"[^>]*x-show="! searching"#', $nav);
        $this->assertStringContainsString('x-show="noMatches"', $nav);
        $this->assertStringContainsString('No matches', $nav);
    }

    public function test_children_of_a_matching_parent_stay_visible(): void
    {
        $nav = $this->nav($this->visit('/admin'));

        // "Members" lists its ancestor ("team") so typing "team" shows the whole section
        $this->assertMatchesRegularExpression("#matches\\('members', '[^']*', '[^']*team#", $nav);
    }

    public function test_a_panel_without_search_has_no_search_box_or_filter_attributes(): void
    {
        $user = User::factory()->create();
        $nav = $this->nav($this->actingAs($user)->get('/portal')->assertOk());

        $this->assertStringNotContainsString('type="search"', $nav);
        $this->assertStringNotContainsString('matches(', $nav);
        $this->assertStringNotContainsString('fi-drilldown-shortcuts', $nav);
    }

    public function test_top_level_has_pinned_and_recent_lists_and_other_levels_only_the_pin_toggle(): void
    {
        $this->seed(DatabaseSeeder::class);

        $root = $this->nav($this->visit('/admin'));
        $this->assertStringContainsString('fi-drilldown-shortcuts', $root);
        $this->assertStringContainsString('x-for="entry in pinned"', $root);
        $this->assertStringContainsString('x-for="entry in recent"', $root);
        $this->assertStringContainsString('Pinned', $root);
        $this->assertStringContainsString('Recent', $root);

        $project = Project::firstOrFail();
        $level = $this->nav($this->visit("/admin/projects/{$project->id}"));
        $this->assertStringContainsString('Pin this page', $level);
        $this->assertStringNotContainsString('x-for="entry in pinned"', $level);
    }

    public function test_the_shortcuts_are_stored_per_panel_and_per_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'demo@example.com')->firstOrFail();
        $member = User::where('email', 'member@example.com')->firstOrFail();

        $a = (string) $this->actingAs($admin)->get('/admin')->getContent();
        $b = (string) $this->actingAs($member)->get('/admin')->getContent();

        $this->assertStringContainsString("fi-drilldown:admin:{$admin->id}", $a);
        $this->assertStringContainsString("fi-drilldown:admin:{$member->id}", $b);
        $this->assertStringNotContainsString("fi-drilldown:admin:{$member->id}", $a);
    }

    public function test_the_current_page_trail_is_what_gets_remembered(): void
    {
        $this->seed(DatabaseSeeder::class);
        $project = Project::firstOrFail();

        $html = (string) $this->visit("/admin/projects/{$project->id}/tasks")->getContent();

        // the nav's Alpine data carries the page's trail ("Project › Tasks") for the Recent / Pin lists
        $this->assertStringContainsString('\u0022label\u0022:\u0022'.$project->name.' › Tasks\u0022', $html);
    }

    public function test_a_long_client_project_list_is_shortened_with_a_view_all_link(): void
    {
        $this->seed(DatabaseSeeder::class);
        $client = Client::where('name', 'Acme Corp')->firstOrFail();
        $client->projects()->createMany(array_map(fn (int $i) => ['name' => "Extra project {$i}"], range(1, 6)));
        $total = $client->projects()->count();

        $response = $this->visit("/admin/clients/{$client->id}/projects");
        $nav = $this->nav($response);

        // 5 shown, the rest behind a link that goes to the client's projects table
        $this->assertSame(5, substr_count($nav, 'fi-sidebar-item-btn'));
        $this->assertStringContainsString('View all ('.($total - 5).')', $nav);
        $this->assertStringContainsString('href="'.url("/admin/clients/{$client->id}/projects").'"', $nav);
    }

    public function test_search_labels_are_translated(): void
    {
        $nav = $this->nav($this->visit('/admin?lang=ja'));

        $this->assertStringContainsString('placeholder="メニューを検索…"', $nav);
        $this->assertStringContainsString('一致する項目がありません', $nav);
        $this->assertStringContainsString('最近', $nav);
    }
}
