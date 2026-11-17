<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use SolutionForest\DrilldownNav\DrilldownNavPlugin;
use Tests\TestCase;

/** Record menus from the resource, live refresh, server-side open state, accordion. */
class Step2Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'demo@example.com', 'role' => 'admin']);
    }

    private function sidebar(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $response->getContent() ?: '', $m));

        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<span class="fi-drilldown-initial"[^>]*>.*?</span>#s', '', (string) preg_replace('#<(?:p class="fi-drilldown-empty"|div class="fi-drilldown-shortcuts"|div class="fi-drilldown-hook").*$#s', '', $m[1])))));
    }

    public function test_record_pages_builds_the_menu_from_the_resources_own_pages(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $project = Project::create(['name' => 'Alpha', 'client_id' => $client->id]);

        $text = $this->sidebar($this->actingAs($this->admin())->get("/auto/projects/{$project->id}/tasks"));

        // View, Edit (settings/general), Documents, Tasks, Time: every page whose route needs only the record
        $this->assertMatchesRegularExpression('/^Back Alpha /', $text);
        foreach (['Tasks', 'Time', 'Documents'] as $label) {
            $this->assertStringContainsString($label, $text);
        }
        // pages that need more than the record (/{record}/{section}) are left out
        $this->assertStringNotContainsString('Links', $text);
    }

    public function test_a_resource_can_declare_its_own_record_menu(): void
    {
        $client = Client::create(['name' => 'Acme']);

        $text = $this->sidebar($this->actingAs($this->admin())->get("/auto/clients/{$client->id}"));

        $this->assertSame('Back Acme Overview Projects Invoices Contacts', $text);
    }

    public function test_an_explicit_record_menu_wins_over_the_resources_own(): void
    {
        $client = Client::create(['name' => 'Acme']);

        $html = (string) $this->actingAs($this->admin())->get("/admin/clients/{$client->id}")->getContent();

        // the admin panel's ->record() menu has a badge on Invoices; the resource's own menu has none
        $this->assertMatchesRegularExpression('#Invoices.*?fi-badge#s', $this->sidebarHtml($html));
    }

    private function sidebarHtml(string $html): string
    {
        preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $html, $m);

        return $m[1] ?? '';
    }

    public function test_poll_badges_adds_a_livewire_poll_only_where_enabled(): void
    {
        $user = $this->admin();

        $this->assertStringContainsString('wire:poll.30s', (string) $this->actingAs($user)->get('/auto')->getContent());
        $this->assertStringNotContainsString('wire:poll', $this->sidebarHtml((string) $this->actingAs($user)->get('/admin')->getContent()));
    }

    public function test_poll_badges_rejects_a_bad_interval(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DrilldownNavPlugin::make()->pollBadges('soon');
    }

    public function test_open_state_comes_from_the_cookie_on_the_server(): void
    {
        $user = $this->admin();
        $html = (string) $this->actingAs($user)->get('/admin/demo/team/members')->getContent();
        $key = $this->sectionKey($html, 'Subscription');

        // closed by default (and hidden before Alpine boots)
        $this->assertStringContainsString('open: false,', $this->sectionMarkup($html, $key));

        // the user opened it earlier: the server renders it open (no flash), from the raw cookie the browser sends
        $open = (string) $this->actingAs($user)->withUnencryptedCookie(DrilldownNavPlugin::OPEN_STATE_COOKIE, (string) json_encode([$key => 1]))
            ->get('/admin/demo/team/members')->getContent();
        $this->assertStringContainsString('open: true,', $this->sectionMarkup($open, $key));
    }

    /** Key of the inline section whose row shows $label. */
    private function sectionKey(string $html, string $label): string
    {
        preg_match_all('#data-section="([a-f0-9]{10})"#', $html, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[1] as $i => [$key, $offset]) {
            $end = $m[1][$i + 1][1] ?? strlen($html);
            $row = substr($html, $offset, min(5000, $end - $offset));
            if (preg_match('#>\s*'.preg_quote($label, '#').'\s*<#', $row) === 1) {
                return $key;
            }
        }
        $this->fail("no inline section labelled {$label}");
    }

    /** The x-data of the section with $key (the text between the <li> and its data-section attribute). */
    private function sectionMarkup(string $html, string $key): string
    {
        $at = (int) strpos($html, 'data-section="'.$key.'"');

        return substr($html, (int) strrpos(substr($html, 0, $at), '<li'), $at - (int) strrpos(substr($html, 0, $at), '<li'));
    }

    public function test_a_tampered_open_state_cookie_is_ignored(): void
    {
        $html = (string) $this->actingAs($this->admin())
            ->withUnencryptedCookie(DrilldownNavPlugin::OPEN_STATE_COOKIE, '{"<script>":1,"x":"y"')
            ->get('/admin/demo/team/members')->getContent();

        $this->assertStringNotContainsString('<script>":1', $html);
        $this->assertStringContainsString('fi-drilldown-nav', $html);
    }

    public function test_accordion_closes_siblings_and_ignores_stored_state_off_the_path(): void
    {
        $user = $this->admin();
        $html = (string) $this->actingAs($user)->get('/portal')->getContent();

        $this->assertStringContainsString('x-on:fi-drilldown-accordion.window', $html);
        $this->assertStringContainsString("\$dispatch('fi-drilldown-accordion'", $html);
        $this->assertStringNotContainsString('x-on:fi-drilldown-accordion.window', $this->sidebarHtml((string) $this->actingAs($user)->get('/admin')->getContent()));
    }
}
