<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Livewire\Concerns;

use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Livewire\Attributes\Renderless;
use SolutionForest\DrilldownNav\DrilldownNavPlugin;
use SolutionForest\DrilldownNav\RequestState;

/**
 * Hides Filament's own navigation list while THIS sidebar renders, so the drill-down menu
 * replaces it there, and only there: the top bar (`->topNavigation()`), breadcrumbs and any
 * other code calling `Filament::getNavigation()` still get the full native navigation.
 *
 * Also serves the "search all levels" index (DrilldownNavPlugin::searchable(allLevels: true)).
 *
 * Already used by the plugin's sidebar component. Use it on your own component only if your
 * panel sets `->sidebarLivewireComponent(...)`.
 */
trait HidesNativeNavigation
{
    #[Unexposed]
    public function renderingHidesNativeNavigation(): void
    {
        RequestState::current()->hidingNativeNavigation = true;
    }

    #[Unexposed]
    public function renderedHidesNativeNavigation(): void
    {
        RequestState::current()->hidingNativeNavigation = false;
    }

    /**
     * Every page in the menu the current user may see, for searching levels that are not on screen.
     * Loaded by the search box on first use; empty unless the panel's plugin enables `allLevels`.
     *
     * @return list<array{label: string, url: string, trail: list<string>, depth: int}>
     */
    #[Exposed]
    #[Renderless]
    public function drilldownSearchIndex(): array
    {
        $plugin = DrilldownNavPlugin::current();

        return $plugin !== null && $plugin->searchesAllLevels() ? $plugin->flatten() : [];
    }

    /**
     * Pin or unpin a page (DrilldownNavPlugin::pinnable(database: true)).
     *
     * @return list<array{url: string, label: string}>
     */
    #[Exposed]
    #[Renderless]
    public function drilldownTogglePin(string $url, string $label): array
    {
        $plugin = DrilldownNavPlugin::current();

        return $plugin !== null && $plugin->storesPinsInDatabase() ? $plugin->togglePin($url, $label) : [];
    }

    /**
     * Drop a pinned page that no longer opens (found by the sidebar's daily check).
     *
     * @return list<array{url: string, label: string}>
     */
    #[Exposed]
    #[Renderless]
    public function drilldownForgetPin(string $url): array
    {
        $plugin = DrilldownNavPlugin::current();

        return $plugin !== null && $plugin->storesPinsInDatabase() ? $plugin->forgetPin($url) : [];
    }
}
