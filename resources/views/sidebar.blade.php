@php
    $resolver = new \SolutionForest\DrilldownNav\Tree\ActivePathResolver;
    $tree = \SolutionForest\DrilldownNav\DrilldownNavPlugin::get()->getTree();
    $path = $resolver->resolve($tree, '/' . request()->path());
    $level = $resolver->level($tree, $path);
    $activeIds = array_map(fn ($n) => $n->getId(), $path);
@endphp

<nav
    x-data
    class="fi-drilldown-nav"
    data-depth="{{ $level['depth'] }}"
>
    @if ($level['parent'])
        @php $grand = $level['depth'] > 1 ? $path[$level['depth'] - 2] : null; @endphp
        <a
            href="{{ $grand?->getUrl() ?? url(filament()->getCurrentPanel()->getPath()) }}"
            wire:navigate
            class="fi-drilldown-back"
        >
            <x-filament::icon icon="heroicon-m-chevron-left" style="width:1.25rem;height:1.25rem" />
            {{ $level['parent']->getLabel() }}
        </a>
    @endif

    <ul class="fi-drilldown-list">
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

<style>
    .fi-drilldown-nav { display: flex; flex-direction: column; gap: 0.25rem; }
    .fi-drilldown-list { display: flex; flex-direction: column; gap: 0.25rem; margin: 0; padding: 0; list-style: none; animation: fi-drilldown-in 0.2s ease-out; }
    .fi-drilldown-back { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--gray-500); text-decoration: none; }
    .fi-drilldown-back:hover { color: var(--gray-950); }
    .dark .fi-drilldown-back { color: var(--gray-400); }
    .dark .fi-drilldown-back:hover { color: #fff; }
    @keyframes fi-drilldown-in { from { opacity: 0; transform: translateX(-12px); } to { opacity: 1; transform: none; } }
</style>
