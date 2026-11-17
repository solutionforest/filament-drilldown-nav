<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Livewire;

use Filament\Livewire\Sidebar;
use SolutionForest\DrilldownNav\Livewire\Concerns\HidesNativeNavigation;

/** Filament's sidebar, with its own navigation list replaced by the drill-down menu. */
class DrilldownSidebar extends Sidebar
{
    use HidesNativeNavigation;
}
