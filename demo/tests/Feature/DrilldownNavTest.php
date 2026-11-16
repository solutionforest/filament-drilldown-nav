<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrilldownNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_level_shows_top_menu(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')
            ->assertOk()->assertSee('Sites')->assertSee('Settings')->assertDontSee('Acme Corp');
    }

    public function test_level_three_shows_only_that_branch_with_back_link(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/demo/sites/acme')
            ->assertOk()->assertSee('Pages')->assertSee('Media')
            ->assertSee('fi-drilldown-back', false)
            ->assertDontSee('Globex')->assertDontSee('Settings');
    }

    public function test_level_four_shows_leaf_siblings(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/demo/sites/acme/pages/home')
            ->assertOk()->assertSee('Home page')->assertSee('About page')->assertDontSee('Acme Corp');
    }
}
