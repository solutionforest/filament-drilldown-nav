<?php

namespace Tests\Feature;

use App\Models\Project;
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

    private function project(): Project
    {
        $project = Project::create(['name' => 'Website redesign']);
        $project->documents()->create(['title' => 'Refund policy']);

        return $project;
    }

    public function test_clicking_an_project_shows_its_own_menu(): void
    {
        $project = $this->project();

        foreach (['', '/tasks'] as $suffix) {
            $this->get_("/admin/projects/{$project->id}{$suffix}")->assertOk()
                ->assertSeeInOrder(['fi-drilldown-title', 'Website redesign', 'Overview', 'Tasks', 'Files', 'Settings'], false)
                ->assertSee('href="'.url('/admin/projects').'"', false) // Back to the list
                ->assertDontSee('Docs');
        }
    }

    public function test_files_sub_menu_inside_an_project(): void
    {
        $project = $this->project();
        $doc = $project->documents()->first();

        foreach (["/admin/projects/{$project->id}/documents", "/admin/projects/{$project->id}/documents/{$doc->id}/edit", "/admin/projects/{$project->id}/links"] as $url) {
            $this->get_($url)->assertOk()
                ->assertSeeInOrder(['fi-drilldown-title', 'Files', 'Documents', 'Links'], false)
                ->assertSee('href="'.url("/admin/projects/{$project->id}").'"', false) // Back to the project menu
                ->assertDontSee('Tasks');
        }
    }

    public function test_settings_group_inside_an_project(): void
    {
        $project = $this->project();

        foreach (['/settings/general', '/settings/advanced'] as $suffix) {
            $this->get_("/admin/projects/{$project->id}{$suffix}")->assertOk()
                ->assertSeeInOrder(['fi-drilldown-title', 'Settings', 'General', 'Advanced'], false)
                ->assertDontSee('Tasks');
        }
    }

    public function test_project_list_and_create_stay_on_root_level(): void
    {
        foreach (['/admin/projects', '/admin/projects/create'] as $url) {
            $this->get_($url)->assertOk()->assertSee('Docs')->assertDontSee('class="fi-drilldown-back"', false);
        }
    }
}
