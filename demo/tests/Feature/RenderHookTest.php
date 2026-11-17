<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SolutionForest\DrilldownNav\DrilldownNavHook;
use Tests\TestCase;

/** The sidebar exposes Filament render hooks (https://filamentphp.com/docs/5.x/advanced/render-hooks). */
class RenderHookTest extends TestCase
{
    use RefreshDatabase;

    private function html(string $url, ?User $user = null): string
    {
        return (string) $this->actingAs($user ?? User::factory()->create(['email' => 'demo@example.com', 'role' => 'admin']))->get($url)->assertOk()->getContent();
    }

    public function test_a_hook_registered_on_the_panel_renders_inside_the_navigation_with_the_level_data(): void
    {
        $html = $this->html('/admin');

        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $html, $m));
        $this->assertStringContainsString('data-demo-hook="nav-end" data-depth="0"', $m[1]);
        $this->assertStringContainsString('Help', $m[1]);
    }

    public function test_hooks_receive_the_depth_and_title_of_the_level(): void
    {
        $project = Project::create(['name' => 'Alpha']);

        $html = $this->html("/admin/projects/{$project->id}");

        $this->assertStringContainsString('data-demo-hook="nav-end" data-depth="2"', $html); // Projects > Alpha
        $this->assertStringContainsString('You are inside: Alpha', $html);
    }

    public function test_header_hooks_only_render_on_levels_that_have_a_header(): void
    {
        $this->assertStringNotContainsString('data-demo-hook="header-after"', $this->html('/admin')); // top level: no header
    }

    public function test_hooks_are_scoped_to_their_panel(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com', 'role' => 'admin']);

        $portal = $this->html('/portal', $user);

        $this->assertStringContainsString('fi-drilldown-nav', $portal);
        $this->assertStringNotContainsString('data-demo-hook', $portal); // registered on `admin` only
    }

    public function test_every_documented_hook_name_is_rendered_by_the_sidebar(): void
    {
        $view = file_get_contents(__DIR__.'/../../../resources/views/sidebar.blade.php');
        $constants = (new \ReflectionClass(DrilldownNavHook::class))->getConstants();

        $this->assertCount(6, $constants);
        foreach (array_keys($constants) as $name) {
            $this->assertStringContainsString('DrilldownNavHook::'.$name, $view, "{$name} is documented but not rendered");
        }
    }
}
