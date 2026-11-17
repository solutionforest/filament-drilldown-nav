<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SolutionForest\DrilldownNav\DrilldownNavPlugin;
use SolutionForest\DrilldownNav\Livewire\DrilldownSidebar;
use Tests\TestCase;

/** Search across all levels, the shortcut key, and the flattened menu. */
class Step3Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'demo@example.com', 'role' => 'admin']);
    }

    public function test_flatten_lists_every_visible_page_with_its_trail(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        $entries = DrilldownNavPlugin::current()?->flatten() ?? [];
        $byLabel = collect($entries)->keyBy('label');

        $this->assertTrue($byLabel->has('Install'), 'a page four levels down');
        $this->assertSame(['Docs', 'Guides', 'Getting started'], $byLabel['Install']['trail']);
        $this->assertSame(3, $byLabel['Install']['depth']);
        $this->assertSame(0, $byLabel['Projects']['depth']);
        // every URL once
        $this->assertSame(count($entries), collect($entries)->pluck('url')->unique()->count());
    }

    public function test_flatten_respects_visibility_and_the_limit(): void
    {
        $member = User::factory()->create(['role' => 'client']);
        $this->actingAs($member);
        Filament::setCurrentPanel('admin');
        $plugin = DrilldownNavPlugin::current();

        $labels = collect($plugin?->flatten() ?? [])->pluck('label');
        $this->assertNotContains('Clients', $labels); // staff only
        $this->assertCount(3, $plugin?->flatten(limit: 3) ?? []);
    }

    public function test_the_sidebar_serves_the_index_only_when_all_levels_search_is_on(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');
        $index = Livewire::test(DrilldownSidebar::class)->instance()->drilldownSearchIndex();
        $this->assertNotEmpty($index);

        Filament::setCurrentPanel('portal'); // searchable() is off there
        $this->assertSame([], Livewire::test(DrilldownSidebar::class)->instance()->drilldownSearchIndex());
    }

    public function test_search_box_has_other_levels_results_and_the_shortcut(): void
    {
        $html = (string) $this->actingAs($this->admin())->get('/admin')->getContent();

        $this->assertStringContainsString('aria-keyshortcuts="/"', $html);
        $this->assertStringContainsString('class="fi-drilldown-other"', $html);
        $this->assertStringContainsString('In other menus', $html);
        $this->assertStringContainsString('drilldownSearchIndex()', $html);
    }

    public function test_shortcut_must_be_one_character(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DrilldownNavPlugin::make()->searchable(shortcut: 'ctrl+k');
    }
}
