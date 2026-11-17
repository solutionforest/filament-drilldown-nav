<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Livewire\Sidebar;
use Filament\Navigation\NavigationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Testing\TestResponse;
use SolutionForest\DrilldownNav\DrilldownNavPlugin;
use SolutionForest\DrilldownNav\Livewire\DrilldownSidebar;
use Tests\TestCase;

/** 1.0 readiness: the plugin replaces Filament's sidebar list only, nothing else. */
class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'demo@example.com']);
    }

    /** Visible text of the drill-down sidebar only (whitespace collapsed). */
    private function sidebar(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $response->getContent() ?: '', $m));

        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<span class="fi-drilldown-initial"[^>]*>.*?</span>#s', '', (string) preg_replace('#<(?:p class="fi-drilldown-empty"|div class="fi-drilldown-shortcuts").*$#s', '', preg_replace('#<(p|div)\s[^>]*data-demo-hook.*?</\1>#s', '', $m[1]))))));
    }

    /** Labels of Filament's own navigation, as other code sees it. */
    private function nativeLabels(): array
    {
        return collect(Filament::getNavigation())
            ->flatMap(fn (NavigationGroup $group) => [$group->getLabel(), ...collect($group->getItems())->map->getLabel()])
            ->filter()->map(fn ($label) => (string) $label)->values()->all();
    }

    public function test_filament_navigation_stays_intact_outside_the_sidebar(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk();

        // e.g. a command palette or breadcrumbs calling Filament::getNavigation()
        $labels = $this->nativeLabels();
        $this->assertContains('Reports', $labels);
        $this->assertContains('Billing', $labels);
    }

    public function test_the_native_list_is_empty_in_the_sidebar(): void
    {
        $html = (string) $this->actingAs($this->admin())->get('/admin')->getContent();

        $this->assertSame(1, preg_match('#<ul class="fi-sidebar-nav-groups">(.*?)</ul>#s', $html, $m));
        $this->assertSame('', trim(strip_tags($m[1])), 'Filament\'s own list must not render next to the drill-down menu');
    }

    public function test_top_navigation_keeps_filament_menu_in_the_top_bar(): void
    {
        $panel = Filament::getPanel('admin');
        $panel->topNavigation();

        try {
            $response = $this->actingAs($this->admin())->get('/admin');
            $response->assertOk();

            $this->assertSame(1, preg_match('#<ul class="fi-topbar-nav-groups">(.*?)</ul>\s*@?#s', (string) $response->getContent(), $m));
            $this->assertStringContainsString('Reports', strip_tags($m[1]));

            // and the drill-down menu (mobile drawer) is unchanged, with no duplicated native items
            $text = $this->sidebar($response);
            $this->assertSame(1, substr_count($text, 'Reports'));
        } finally {
            $panel->topNavigation(false);
        }
    }

    public function test_native_items_are_not_duplicated_when_filament_built_its_navigation_first(): void
    {
        $user = $this->admin();
        $before = $this->sidebar($this->actingAs($user)->get('/admin/reports/billing'));

        // Filament's own navigation mounted earlier in the same request (top bar, breadcrumbs ...)
        app('events')->listen(RouteMatched::class, function (): void {
            Filament::setCurrentPanel('admin');
            Filament::getNavigation();
        });

        $after = $this->sidebar($this->actingAs($user)->get('/admin/reports/billing'));
        $this->assertSame($before, $after);
    }

    public function test_the_plugin_uses_its_own_sidebar_component(): void
    {
        $this->assertSame(DrilldownSidebar::class, Filament::getPanel('admin')->getSidebarLivewireComponent());
        $this->assertTrue(is_subclass_of(DrilldownSidebar::class, Sidebar::class));
    }

    public function test_a_custom_sidebar_component_is_left_alone(): void
    {
        $panel = Filament::getPanel('admin');
        $panel->sidebarLivewireComponent(CustomSidebar::class);

        try {
            DrilldownNavPlugin::make()->register($panel);
            $this->assertSame(CustomSidebar::class, $panel->getSidebarLivewireComponent());
        } finally {
            $panel->sidebarLivewireComponent(DrilldownSidebar::class);
        }
    }

    public function test_pinned_and_recent_storage_is_separate_per_tenant(): void
    {
        $user = User::factory()->create();
        $teams = [];
        foreach (['acme', 'globex'] as $slug) {
            $user->teams()->attach($teams[$slug] = Team::create(['name' => ucfirst($slug), 'slug' => $slug]));
        }

        $acme = (string) $this->actingAs($user)->get('http://workspace.localhost/app/acme')->getContent();
        $globex = (string) $this->actingAs($user)->get('http://workspace.localhost/app/globex')->getContent();

        $this->assertStringContainsString("fi-drilldown:workspace:t{$teams['acme']->id}:{$user->id}", $acme);
        $this->assertStringContainsString("fi-drilldown:workspace:t{$teams['globex']->id}:{$user->id}", $globex);
        // and stored entries are only shown under the tenant's own URL
        $this->assertMatchesRegularExpression('#root[^,]*app[^,]*acme#', $acme);
        $this->assertMatchesRegularExpression('#root[^,]*app[^,]*globex#', $globex);
    }

    public function test_stylesheet_is_a_linked_asset_not_inline_css(): void
    {
        $html = (string) $this->actingAs($this->admin())->get('/admin')->getContent();

        $this->assertMatchesRegularExpression('#<link[^>]+filament-drilldown-nav\.css#', $html);
        $this->assertStringNotContainsString('.fi-drilldown-nav {', $html);
        $this->assertDoesNotMatchRegularExpression('#class="fi-drilldown[^"]*"[^>]*style="#', $html);
    }

    public function test_icon_less_items_show_an_initial_in_the_collapsed_sidebar(): void
    {
        // the admin panel is collapsible on desktop; "Docs" has an icon, native "By project" does not
        $html = (string) $this->actingAs($this->admin())->get('/admin/reports/usage')->getContent();

        $this->assertStringContainsString('<span class="fi-drilldown-initial" aria-hidden="true">B</span>', $html);
    }
}

class CustomSidebar extends Sidebar {}
