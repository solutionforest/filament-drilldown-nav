<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A Filament cluster (native sub-navigation beside the content) next to the drill-down sidebar. */
class ClusterTest extends TestCase
{
    use RefreshDatabase;

    private function html(string $url): string
    {
        $user = User::factory()->create(['email' => 'demo@example.com', 'role' => 'admin']);

        return (string) $this->actingAs($user)->get($url)->assertOk()->getContent();
    }

    private function nav(string $html): string
    {
        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $html, $m));

        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<(p|div)\s[^>]*data-demo-hook.*?</\1>#s', '', $m[1]))));
    }

    public function test_the_cluster_appears_in_the_menu_through_from_filament_navigation(): void
    {
        $this->assertStringContainsString('Reporting', $this->nav($this->html('/admin')));
    }

    public function test_cluster_pages_render_their_own_sub_navigation_next_to_the_sidebar(): void
    {
        $html = $this->html('/admin/cluster-reports/overview');

        $this->assertStringContainsString('fi-page-sub-navigation', $html); // Filament's cluster sub-navigation
        $this->assertStringContainsString('Exports', $html);                // the cluster's other page
        $this->assertSame(1, substr_count($html, 'class="fi-drilldown-nav"')); // and still exactly one drill-down sidebar
    }

    public function test_the_cluster_item_stays_current_on_its_pages(): void
    {
        $html = $this->html('/admin/cluster-reports/exports');

        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $html, $m));
        $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>.*?Reporting#s', $m[1]);
    }

    public function test_the_native_navigation_is_not_emptied_for_the_cluster_sub_navigation(): void
    {
        $html = $this->html('/admin/cluster-reports/overview');

        $this->assertStringContainsString('Overview', $html);
        $this->assertStringContainsString('Exports', $html);
    }
}
