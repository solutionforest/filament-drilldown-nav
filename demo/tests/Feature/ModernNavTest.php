<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Phase B: click-to-expand sections, headings, dividers, collapsed (icon-only) mode, accessibility. */
class ModernNavTest extends TestCase
{
    use RefreshDatabase;

    private function visit(string $url): TestResponse
    {
        return $this->actingAs(User::factory()->create())->get($url)->assertOk();
    }

    /** The inline <li> of an expandable section, from its opening tag to the next section. */
    private function section(TestResponse $response, string $label): string
    {
        $html = (string) $response->getContent();
        $this->assertSame(1, preg_match('#<li\s[^>]*fi-drilldown-inline[^>]*>(?:(?!<li\s[^>]*fi-drilldown-inline).)*?'.preg_quote($label, '#').'.*?</ul>\s*</li>#s', $html, $m), "section {$label}");

        return $m[0];
    }

    public function test_root_has_a_heading_a_divider_and_expandable_sections(): void
    {
        $html = (string) $this->visit('/admin')->getContent();

        $this->assertStringContainsString('class="fi-drilldown-heading"', $html);
        $this->assertStringContainsString('Workspace', $html);
        $this->assertStringContainsString('role="separator"', $html);
        $this->assertStringContainsString('fi-drilldown-inline', $html);
    }

    public function test_inline_children_do_not_open_a_new_level(): void
    {
        $html = (string) $this->visit('/admin/demo/team/members')->getContent();

        $this->assertStringNotContainsString('class="fi-drilldown-back"', $html); // still the root level
        $team = $this->section($this->visit('/admin/demo/team/members'), 'Team');
        $this->assertStringContainsString('Members', $team);
        $this->assertStringContainsString('Roles', $team);
    }

    public function test_the_section_containing_the_current_page_starts_expanded_and_marks_it(): void
    {
        $team = $this->section($this->visit('/admin/demo/team/members'), 'Team');

        $this->assertStringContainsString('fi-sidebar-item-has-active-child-items', $team);
        $this->assertStringContainsString('open: true ||', $team);
        $this->assertMatchesRegularExpression('#aria-current="page"#', $team);
    }

    public function test_other_sections_stay_collapsed_unless_default_expanded(): void
    {
        $subscription = $this->section($this->visit('/admin/demo/team/members'), 'Subscription');
        $this->assertStringContainsString('open: false ||', $subscription);
        $this->assertStringContainsString("?? '0'", $subscription); // remembered state, collapsed by default

        $team = $this->section($this->visit('/admin/demo/subscription/overview'), 'Team');
        $this->assertStringContainsString("?? '1'", $team); // ->defaultExpanded()
    }

    public function test_nested_sections_expand_down_to_the_current_page(): void
    {
        $response = $this->visit('/admin/demo/subscription/invoices/unpaid');
        $html = (string) $response->getContent();

        $this->assertStringNotContainsString('class="fi-drilldown-back"', $html);
        $subscription = $this->section($response, 'Subscription');
        $this->assertSame(2, substr_count($subscription, 'open: true ||'), 'Subscription and Invoices are both open');
        $this->assertStringContainsString('Unpaid', $subscription);
    }

    public function test_toggles_are_accessible_buttons(): void
    {
        $team = $this->section($this->visit('/admin'), 'Team');

        $this->assertStringContainsString('type="button"', $team);
        $this->assertStringContainsString('x-bind:aria-expanded="open.toString()"', $team);
    }

    public function test_every_expandable_row_shows_a_chevron_and_nested_ones_a_guide_dot(): void
    {
        $response = $this->visit('/admin/demo/subscription/invoices/unpaid');

        $subscription = $this->section($response, 'Subscription');
        // Subscription and its nested "Invoices" both render a toggle chevron
        $this->assertSame(2, substr_count($subscription, 'fi-drilldown-toggle-icon'));
        // the nested icon-less row draws Filament's guide line + dot
        $this->assertStringContainsString('fi-sidebar-item-grouped-border-part', $subscription);
    }

    public function test_drilldown_still_works_next_to_inline_sections(): void
    {
        $project = Project::create(['name' => 'Alpha']);

        $html = (string) $this->visit("/admin/projects/{$project->id}")->getContent();

        $this->assertStringContainsString('class="fi-drilldown-back"', $html);
        $this->assertStringNotContainsString('Subscription', $html); // another level: not in the project's menu
    }

    public function test_a_panel_can_default_to_inline_children(): void
    {
        $html = (string) $this->visit('/portal')->getContent();

        // "Support" has children but opens in place: no new level, children already in the sidebar
        $this->assertStringNotContainsString('class="fi-drilldown-back"', $html);
        $this->assertStringContainsString('Tickets', $html);
        $this->assertStringContainsString('Contact', $html);

        $deep = (string) $this->visit('/portal/demo/support/tickets')->getContent();
        $this->assertStringNotContainsString('class="fi-drilldown-back"', $deep);
        $this->assertStringContainsString('open: true ||', $deep);
    }

    public function test_collapsed_icon_only_mode_hides_text_and_adds_a_tooltip(): void
    {
        $project = Project::create(['name' => 'Alpha']);
        $html = (string) $this->visit("/admin/projects/{$project->id}")->getContent();

        // Back: tooltip + aria-label when the sidebar is collapsed; text and title hidden
        $this->assertStringContainsString('x-tooltip.html="tooltip"', $html);
        // the plugin's Back link must not use a helper component that only newer Filament versions ship
        $this->assertSame(1, preg_match('#<a\s[^>]*class="fi-drilldown-back"[^>]*>#s', $html, $backLink));
        $this->assertStringNotContainsString('filamentSidebarTooltip', $backLink[0]);
        $this->assertSame(1, preg_match('#<div\s[^>]*class="fi-drilldown-title"[^>]*>#s', $html, $title));
        $this->assertStringContainsString('x-show="$store.sidebar.isOpen"', $title[0]);

        $root = (string) $this->visit('/admin')->getContent();
        $this->assertMatchesRegularExpression('#<li class="fi-drilldown-heading"[^>]*x-show="\$store\.sidebar\.isOpen#', $root);
    }

    public function test_level_title_is_a_focus_target_for_keyboard_and_screen_readers(): void
    {
        $project = Project::create(['name' => 'Alpha']);
        $html = (string) $this->visit("/admin/projects/{$project->id}")->getContent();

        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('x-ref="title"', $html);
        $this->assertStringContainsString('prefers-reduced-motion', self::stylesheet());
    }

    private static function stylesheet(): string
    {
        return (string) file_get_contents(__DIR__.'/../../../resources/dist/filament-drilldown-nav.css');
    }
}
