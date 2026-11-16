@php
    $resolver = new \SolutionForest\DrilldownNav\Tree\ActivePathResolver;
    $tree = \SolutionForest\DrilldownNav\DrilldownNavPlugin::get()->getTree();
    $path = $resolver->resolve($tree, '/' . request()->path());
    $level = $resolver->level($tree, $path);
    $activeIds = array_map(fn ($n) => $n->getId(), $path);
@endphp

<nav
    x-data
    class="fi-drilldown-nav flex flex-col gap-y-1"
    data-depth="{{ $level['depth'] }}"
>
    @if ($level['parent'])
        @php $grand = $level['depth'] > 1 ? $path[$level['depth'] - 2] : null; @endphp
        <a
            href="{{ $grand?->getUrl() ?? url(filament()->getCurrentPanel()->getPath()) }}"
            wire:navigate
            class="flex items-center gap-x-2 px-2 py-2 text-sm font-medium text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white"
        >
            <x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" />
            {{ $level['parent']->getLabel() }}
        </a>
    @endif

    <ul class="flex flex-col gap-y-1" x-transition.opacity>
        @foreach ($level['items'] as $node)
            <x-filament-panels::sidebar.item
                :active="in_array($node->getId(), $activeIds, true)"
                :icon="$node->getIcon()"
                :url="$node->getUrl()"
            >
                {{ $node->getLabel() }}
            </x-filament-panels::sidebar.item>
        @endforeach
    </ul>
</nav>
