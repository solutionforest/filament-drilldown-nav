<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrilldownNavTest extends TestCase
{
    use RefreshDatabase;

    private function get_(string $url)
    {
        return $this->actingAs(User::factory()->create())->get($url);
    }

    public function test_root_level_shows_top_menu_and_group_links_to_first_child(): void
    {
        $this->get_('/admin')->assertOk()
            ->assertSee('Docs')->assertSee('Settings')
            ->assertSee('/admin/demo/settings/general', false)
            ->assertDontSee('class="fi-drilldown-back"', false);
    }

    public function test_dashboard_is_not_active_on_unknown_pages(): void
    {
        $this->get_('/admin/demo/unknown')->assertOk()
            ->assertDontSee('fi-sidebar-item fi-active', false);
    }

    public function test_four_levels_with_back_link_to_parent_level(): void
    {
        $this->get_('/admin/demo/docs/guides/start/install')->assertOk()
            ->assertSee('Install')->assertSee('Configure')
            ->assertSee('Getting started')
            ->assertSee('href="/admin/demo/docs/guides"', false) // Back goes one level up
            ->assertDontSee('Settings');
    }

    private function agent(): Agent
    {
        $agent = Agent::create(['name' => 'Support bot']);
        $agent->documents()->create(['title' => 'Refund policy']);

        return $agent;
    }

    public function test_clicking_an_agent_shows_its_own_menu(): void
    {
        $agent = $this->agent();

        foreach (['', '/playground'] as $suffix) {
            $this->get_("/admin/agents/{$agent->id}{$suffix}")->assertOk()
                ->assertSeeInOrder(['fi-drilldown-title', 'Support bot', 'Overview', 'Playground', 'Knowledge', 'Settings'], false)
                ->assertSee('href="'.url('/admin/agents').'"', false) // Back to the list
                ->assertDontSee('Docs');
        }
    }

    public function test_knowledge_sub_menu_inside_an_agent(): void
    {
        $agent = $this->agent();
        $doc = $agent->documents()->first();

        foreach (["/admin/agents/{$agent->id}/documents", "/admin/agents/{$agent->id}/documents/{$doc->id}/edit", "/admin/agents/{$agent->id}/websites"] as $url) {
            $this->get_($url)->assertOk()
                ->assertSeeInOrder(['fi-drilldown-title', 'Knowledge', 'Documents', 'Websites'], false)
                ->assertSee('href="'.url("/admin/agents/{$agent->id}").'"', false) // Back to the agent menu
                ->assertDontSee('Playground');
        }
    }

    public function test_settings_group_inside_an_agent(): void
    {
        $agent = $this->agent();

        foreach (['/settings/general', '/settings/advanced'] as $suffix) {
            $this->get_("/admin/agents/{$agent->id}{$suffix}")->assertOk()
                ->assertSeeInOrder(['fi-drilldown-title', 'Settings', 'General', 'Advanced'], false)
                ->assertDontSee('Playground');
        }
    }

    public function test_agent_list_and_create_stay_on_root_level(): void
    {
        foreach (['/admin/agents', '/admin/agents/create'] as $url) {
            $this->get_($url)->assertOk()->assertSee('Docs')->assertDontSee('class="fi-drilldown-back"', false);
        }
    }
}
