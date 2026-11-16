<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** NavItem::fromFilamentNavigation(): Filament's own navigation, shown drill-down style. */
class NativeNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Visible text of the drill-down sidebar only (whitespace collapsed). */
    private function sidebar(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $response->getContent() ?: '', $m));

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1])));
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'demo@example.com']);
    }

    public function test_native_group_appears_next_to_hand_written_items(): void
    {
        $text = $this->sidebar($this->actingAs($this->admin())->get('/admin'));

        $this->assertStringContainsString('Dashboard', $text);
        $this->assertStringContainsString('Agents', $text);
        $this->assertStringContainsString('Reports', $text); // native $navigationGroup
        $this->assertStringContainsString('Docs', $text);
        $this->assertStringNotContainsString('Billing', $text); // inside the group, not at the top
    }

    public function test_dashboard_and_agents_are_not_listed_twice(): void
    {
        $text = $this->sidebar($this->actingAs($this->admin())->get('/admin'));

        $this->assertSame(1, substr_count($text, 'Dashboard'));
        $this->assertSame(1, substr_count($text, 'Agents'));
    }

    public function test_group_opens_its_native_items_with_badge_and_sort_order(): void
    {
        $text = $this->sidebar($this->actingAs($this->admin())->get('/admin/reports/billing'));

        $this->assertMatchesRegularExpression('/Back Reports Usage.*12.* Billing/', $text);
    }

    public function test_native_parent_item_opens_a_sub_level(): void
    {
        $text = $this->sidebar($this->actingAs($this->admin())->get('/admin/reports/usage/by-agent'));

        $this->assertMatchesRegularExpression('/^Back Usage By agent$/', $text);
    }

    public function test_native_access_check_hides_the_item_for_other_users(): void
    {
        $other = User::factory()->create(['email' => 'someone@example.com']);

        $text = $this->sidebar($this->actingAs($other)->get('/admin/reports/usage'));

        $this->assertStringNotContainsString('Billing', $text);
        $this->actingAs($other)->get('/admin/reports/billing')->assertForbidden();
    }
}
