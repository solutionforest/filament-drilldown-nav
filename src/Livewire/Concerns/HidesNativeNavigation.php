<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Livewire\Concerns;

use SolutionForest\DrilldownNav\RequestState;

/**
 * Hides Filament's own navigation list while THIS sidebar renders, so the drill-down menu
 * replaces it there, and only there: the top bar (`->topNavigation()`), breadcrumbs and any
 * other code calling `Filament::getNavigation()` still get the full native navigation.
 *
 * Already used by the plugin's sidebar component. Use it on your own component only if your
 * panel sets `->sidebarLivewireComponent(...)`.
 */
trait HidesNativeNavigation
{
    public function renderingHidesNativeNavigation(): void
    {
        RequestState::current()->hidingNativeNavigation = true;
    }

    public function renderedHidesNativeNavigation(): void
    {
        RequestState::current()->hidingNativeNavigation = false;
    }
}
