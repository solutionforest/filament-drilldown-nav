<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SolutionForest\DrilldownNav\DrilldownNavPlugin;
use SolutionForest\DrilldownNav\Livewire\DrilldownSidebar;
use SolutionForest\DrilldownNav\Models\DrilldownPin;
use Tests\TestCase;

/** Pinned pages stored in the database (pinnable(database: true), the `auto` panel). */
class Step4Test extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'demo@example.com'): User
    {
        return User::factory()->create(['email' => $email, 'role' => 'admin']);
    }

    /** @return list<array{url: string, label: string}> */
    private function toggle(string $url, string $label = 'Page'): array
    {
        Filament::setCurrentPanel('auto');

        return Livewire::test(DrilldownSidebar::class)->instance()->drilldownTogglePin($url, $label);
    }

    public function test_pin_and_unpin_through_the_sidebar(): void
    {
        $this->actingAs($this->user());

        $this->assertSame([['url' => '/auto/projects', 'label' => 'Projects']], $this->toggle('/auto/projects', 'Projects'));
        $this->assertSame(1, DrilldownPin::query()->count());
        $this->assertSame([], $this->toggle('/auto/projects', 'Projects'));
        $this->assertSame(0, DrilldownPin::query()->count());
    }

    public function test_pins_belong_to_one_user_and_one_panel(): void
    {
        $this->actingAs($this->user());
        $this->toggle('/auto/projects', 'Projects');

        $this->actingAs($this->user('other@example.com'));
        Filament::setCurrentPanel('auto');
        $this->assertSame([], DrilldownNavPlugin::current()?->getPins());

        // the admin panel keeps pins in the browser: no database pins there
        Filament::setCurrentPanel('admin');
        $this->assertFalse(DrilldownNavPlugin::current()?->storesPinsInDatabase());
    }

    public function test_only_paths_under_the_panel_can_be_pinned(): void
    {
        $this->actingAs($this->user());

        foreach (['//evil.example/x', 'https://evil.example/auto', 'javascript:alert(1)', '/admin/projects', 'auto/projects', "/auto/\x01x"] as $url) {
            $this->assertSame([], $this->toggle($url), $url);
        }
        $this->assertSame(0, DrilldownPin::query()->count());
        $this->assertCount(1, $this->toggle('/auto/projects?tab=open', 'Projects'));
    }

    public function test_the_server_renders_pins_first_and_at_the_top(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $this->toggle('/auto/projects', 'My projects');

        $html = (string) $this->actingAs($user)->get('/auto')->getContent();

        $this->assertStringContainsString('My projects', $html); // in the server-rendered x-data, before any JavaScript
        $this->assertLessThan(strpos($html, 'class="fi-drilldown-list"'), strpos($html, 'class="fi-drilldown-pinned"'), 'Pinned list above the menu');
    }

    public function test_a_pinned_page_keeps_its_label_current(): void
    {
        $user = $this->user();
        $project = Project::create(['name' => 'Alpha']);
        $this->actingAs($user);
        $this->toggle("/auto/projects/{$project->id}/tasks", 'Old name › Tasks');

        $this->actingAs($user)->get("/auto/projects/{$project->id}/tasks")->assertOk();

        $this->assertSame('Alpha › Tasks', DrilldownPin::query()->value('label'));
    }

    public function test_at_most_twenty_pins_are_kept(): void
    {
        $this->actingAs($this->user());

        foreach (range(1, 25) as $i) {
            $pins = $this->toggle("/auto/page-{$i}", "Page {$i}");
        }

        $this->assertCount(DrilldownNavPlugin::MAX_PINS, $pins);
        $this->assertSame('Page 25', $pins[0]['label']);
        $this->assertSame(DrilldownNavPlugin::MAX_PINS, DrilldownPin::query()->count());
    }

    public function test_a_dead_pin_can_be_forgotten(): void
    {
        $this->actingAs($this->user());
        $this->toggle('/auto/projects/999', 'Gone');

        Filament::setCurrentPanel('auto');
        $this->assertSame([], Livewire::test(DrilldownSidebar::class)->instance()->drilldownForgetPin('/auto/projects/999'));
    }

    public function test_a_custom_model_must_extend_the_pin_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DrilldownNavPlugin::make()->pinnable(database: true, model: User::class);
    }
}
