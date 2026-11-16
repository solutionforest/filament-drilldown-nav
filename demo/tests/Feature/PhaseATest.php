<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Facade;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Phase A: multi-panel, Octane-style worker reuse, tenancy + domains.
 *
 * "Octane-style" = several requests served by ONE application instance with only the
 * scoped container instances flushed in between (what Octane does per request), while
 * panels and plugins (registered once at worker boot) stay alive.
 */
class PhaseATest extends TestCase
{
    use RefreshDatabase;

    private function sidebar(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $response->getContent() ?: '', $m), 'drill-down sidebar not rendered exactly once');

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1])));
    }

    private function nextRequest(): void
    {
        // What Octane does between requests: drop scoped instances and cached facade roots.
        $this->app->forgetScopedInstances();
        Facade::clearResolvedInstances();
        $this->flushSession();
        auth()->forgetGuards();
    }

    public function test_second_request_by_another_user_gets_their_own_menu(): void
    {
        $admin = User::factory()->create(['email' => 'demo@example.com']);
        $other = User::factory()->create(['email' => 'someone@example.com']);

        $first = $this->sidebar($this->actingAs($admin)->get('/admin'));
        $this->assertStringContainsString('Audit log', $first); // admin only

        $this->nextRequest();

        $second = $this->sidebar($this->actingAs($other)->get('/admin'));
        $this->assertStringNotContainsString('Audit log', $second); // must NOT leak from request 1
    }

    public function test_record_lookups_are_not_cached_between_requests(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com']);
        $project = Project::create(['name' => 'Alpha']);

        $this->assertStringContainsString('Alpha', $this->sidebar($this->actingAs($user)->get("/admin/projects/{$project->id}")));

        $project->update(['name' => 'Beta']);
        $this->nextRequest();

        $text = $this->sidebar($this->actingAs($user)->get("/admin/projects/{$project->id}"));
        $this->assertStringContainsString('Beta', $text);
        $this->assertStringNotContainsString('Alpha', $text);
    }

    public function test_sidebar_is_rendered_once_however_many_requests_a_worker_serves(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com']);

        foreach (range(1, 4) as $i) {
            $response = $this->actingAs($user)->get('/admin');
            $this->assertSame(1, substr_count((string) $response->getContent(), 'class="fi-drilldown-nav"'), "request {$i}");
            $this->nextRequest();
        }
    }

    public function test_two_panels_with_the_plugin_keep_separate_trees(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com']);

        $admin = $this->sidebar($this->actingAs($user)->get('/admin'));
        $this->nextRequest();
        $portal = $this->sidebar($this->actingAs($user)->get('/portal'));
        $this->nextRequest();
        $adminAgain = $this->sidebar($this->actingAs($user)->get('/admin'));

        $this->assertStringContainsString('Reports', $admin);
        $this->assertStringContainsString('Support', $portal);
        $this->assertStringNotContainsString('Reports', $portal);
        $this->assertSame($admin, $adminAgain);
    }

    public function test_a_panel_without_the_plugin_keeps_filaments_own_sidebar(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com']);

        // first a panel that uses the plugin (registers its hook in a long-lived worker) ...
        $this->actingAs($user)->get('/admin')->assertOk();
        $this->nextRequest();

        // ... then one that does not
        $response = $this->actingAs($user)->get('/plain');
        $response->assertOk();
        $this->assertStringNotContainsString('fi-drilldown-nav', (string) $response->getContent());
        $this->assertStringContainsString('fi-sidebar-nav', (string) $response->getContent());
    }

    public function test_tenant_and_domain_urls_resolve_the_dashboard_as_top_level(): void
    {
        $user = User::factory()->create();
        $team = Team::create(['name' => 'Acme', 'slug' => 'acme']);
        $user->teams()->attach($team);

        $root = $this->actingAs($user)->get('http://workspace.localhost/app/acme');
        $root->assertOk();
        $this->assertSame('Dashboard Billing', preg_replace('/\s*[›>]\s*/', '', $this->sidebar($root)));

        $this->nextRequest();

        $sub = $this->actingAs($user)->get('http://workspace.localhost/app/acme/demo/billing/invoices');
        $text = $this->sidebar($sub);
        $this->assertMatchesRegularExpression('/^Back Billing Invoices Plans$/', $text);
        // Back must stay on the tenant's domain and path, not fall back to a bare panel path
        $this->assertStringContainsString('href="http://workspace.localhost/app/acme"', (string) $sub->getContent());
    }
}
