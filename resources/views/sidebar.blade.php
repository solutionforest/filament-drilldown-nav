@php
    use SolutionForest\DrilldownNav\Tree\ActivePathResolver;

    /** @var \SolutionForest\DrilldownNav\DrilldownNavPlugin $plugin */
    $resolver = new ActivePathResolver;
    $tree = $plugin->getTree();
    $homeUrl = $plugin->getRootUrl();
    $resolved = $resolver->resolve($tree, $plugin->getRequestPath(), ActivePathResolver::normalise($homeUrl));
    $level = $resolver->level($tree, $resolved, $homeUrl);
    $spa = filament()->getCurrentOrDefaultPanel()?->hasSpaMode() ?? false;
@endphp

<nav
    x-data="{
        direction: 'none',
        init() {
            const depth = {{ $level['depth'] }}
            const previous = Number(sessionStorage.getItem('fi-drilldown-depth') ?? depth)
            this.direction = depth > previous ? 'forward' : depth < previous ? 'back' : 'none'
            sessionStorage.setItem('fi-drilldown-depth', depth)
        },
    }"
    class="fi-drilldown-nav"
    aria-label="{{ __('filament-drilldown-nav::messages.navigation') }}"
    data-depth="{{ $level['depth'] }}"
>
    @if ($level['parent'])
        <div class="fi-drilldown-header">
            <a
                href="{{ $level['back'] ?? $homeUrl }}"
                @if ($spa) wire:navigate @endif
                class="fi-drilldown-back"
            >
                <x-filament::icon icon="heroicon-m-chevron-left" style="width:1.25rem;height:1.25rem" />
                {{ __('filament-drilldown-nav::messages.back') }}
            </a>
            <div class="fi-drilldown-title">{{ $level['title'] ?? $level['parent']->getLabel() }}</div>
        </div>
    @endif

    <ul class="fi-drilldown-list" :data-direction="direction">
        @foreach ($level['items'] as $item)
            <x-filament-panels::sidebar.item
                :active="$item === $level['active']"
                :icon="$item->getIcon()"
                :url="$item->getLinkUrl()"
                :badge="$item->getBadge()"
                :badge-color="$item->getBadgeColor()"
            >
                {{ $item->getLabel() }}
                @if ($item->hasChildren() || $item->hasRecordMenu())
                    <x-filament::icon icon="heroicon-m-chevron-right" class="fi-drilldown-chevron" style="width:1rem;height:1rem;display:inline-block;margin-inline-start:0.5rem;vertical-align:middle;opacity:.5" />
                @endif
            </x-filament-panels::sidebar.item>
        @endforeach
    </ul>
</nav>

<style>
    .fi-drilldown-nav { display: flex; flex-direction: column; gap: 0.25rem; }
    .fi-drilldown-list { display: flex; flex-direction: column; gap: 0.25rem; margin: 0; padding: 0; list-style: none; }
    .fi-drilldown-list[data-direction="forward"] { animation: fi-drilldown-forward 0.2s ease-out; }
    .fi-drilldown-list[data-direction="back"] { animation: fi-drilldown-back 0.2s ease-out; }
    .fi-drilldown-header { display: flex; flex-direction: column; padding: 0 0.5rem 0.5rem; }
    .fi-drilldown-back { display: flex; align-items: center; gap: 0.25rem; margin-inline-start: -0.25rem; font-size: 0.875rem; font-weight: 500; color: var(--gray-500); text-decoration: none; }
    .fi-drilldown-back:hover { color: var(--gray-950); }
    .fi-drilldown-title { margin-top: 0.25rem; font-size: 1rem; font-weight: 600; color: var(--gray-950); }
    .dark .fi-drilldown-back { color: var(--gray-400); }
    .dark .fi-drilldown-back:hover, .dark .fi-drilldown-title { color: #fff; }
    @keyframes fi-drilldown-forward { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: none; } }
    @keyframes fi-drilldown-back { from { opacity: 0; transform: translateX(-16px); } to { opacity: 1; transform: none; } }
</style>
