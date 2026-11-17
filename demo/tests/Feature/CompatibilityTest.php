<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Things other sidebar plugins rely on (see docs/compatibility.md). */
class CompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_hook_content_hides_in_the_collapsed_sidebar(): void
    {
        $project = Project::create(['name' => 'Alpha']);
        $html = (string) $this->actingAs(User::factory()->create(['email' => 'demo@example.com']))
            ->get("/admin/projects/{$project->id}")->getContent();

        // the admin panel collapses to icons: free text from hooks must not widen the icon rail
        $this->assertMatchesRegularExpression(
            '#<div class="fi-drilldown-hook" data-hook="filament-drilldown-nav::header.after" x-show="\$store.sidebar.isOpen">\s*<p data-demo-hook="header-after"#',
            $html,
        );
        $this->assertMatchesRegularExpression('#<div class="fi-drilldown-hook" data-hook="filament-drilldown-nav::nav.end" x-show="\$store.sidebar.isOpen">#', $html);
    }

    public function test_hooks_without_content_add_no_wrapper(): void
    {
        $html = (string) $this->actingAs(User::factory()->create(['email' => 'demo@example.com']))->get('/admin')->getContent();

        $this->assertStringNotContainsString('data-hook="filament-drilldown-nav::list.before"', $html);
    }
}
