<?php

namespace Tests\Feature;

use App\Models\Site;
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

    public function test_record_menu_shows_the_sites_own_menu(): void
    {
        $site = Site::create(['name' => 'Acme Corp']);

        foreach (['', '/edit', '/pages'] as $suffix) {
            $this->get_("/admin/sites/{$site->id}{$suffix}")->assertOk()
                ->assertSee('Acme Corp')->assertSee('Overview')->assertSee('Media')
                ->assertSee('class="fi-drilldown-back"', false)
                ->assertDontSee('Docs');
        }
    }

    public function test_site_list_and_create_stay_on_root_level(): void
    {
        foreach (['/admin/sites', '/admin/sites/create'] as $url) {
            $this->get_($url)->assertOk()->assertSee('Docs')->assertDontSee('class="fi-drilldown-back"', false);
        }
    }
}
