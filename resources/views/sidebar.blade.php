@php
    use SolutionForest\DrilldownNav\ChildDisplay;
    use SolutionForest\DrilldownNav\Tree\ActivePathResolver;

    /** @var \SolutionForest\DrilldownNav\DrilldownNavPlugin $plugin */
    $resolver = new ActivePathResolver;
    $tree = $plugin->getTree();
    $homeUrl = $plugin->getRootUrl();
    $inlineByDefault = $plugin->getChildDisplay() === ChildDisplay::Inline;
    $resolved = $resolver->resolve($tree, $plugin->getRequestPath(), ActivePathResolver::normalise($homeUrl));
    $level = $resolver->level($tree, $resolved, $homeUrl, $inlineByDefault);
    $panel = filament()->getCurrentOrDefaultPanel();
    $spa = $panel?->hasSpaMode() ?? false;
    $collapsible = $panel?->isSidebarCollapsibleOnDesktop() ?? false;

    // Shared with the (recursive) item partial.
    $ctx = [
        'pathIds' => array_map(fn ($item) => spl_object_id($item), $resolved['path']),
        'current' => $resolved['path'] === [] ? null : $resolved['path'][array_key_last($resolved['path'])],
        'inlineByDefault' => $inlineByDefault,
        'collapsible' => $collapsible,
        'spa' => $spa,
    ];
@endphp

<nav
    x-data="{
        direction: 'none',
        init() {
            const depth = {{ $level['depth'] }}
            const previous = Number(sessionStorage.getItem('fi-drilldown-depth') ?? depth)
            this.direction = depth > previous ? 'forward' : depth < previous ? 'back' : 'none'
            sessionStorage.setItem('fi-drilldown-depth', depth)

            // Moving between levels: put keyboard/screen-reader focus on the new level's title.
            if (this.direction !== 'none') {
                this.$nextTick(() => this.$refs.title?.focus({ preventScroll: true }))
            }
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
                @if ($collapsible)
                    x-bind:aria-label="$store.sidebar.isOpen ? null : @js(__('filament-drilldown-nav::messages.back'))"
                    x-data="filamentSidebarTooltip"
                    x-tooltip="getTooltip({ content: @js(__('filament-drilldown-nav::messages.back')) })"
                @endif
                class="fi-drilldown-back"
            >
                <x-filament::icon icon="heroicon-m-chevron-left" style="width:1.25rem;height:1.25rem" />
                <span @if ($collapsible) x-show="$store.sidebar.isOpen" @endif>{{ __('filament-drilldown-nav::messages.back') }}</span>
            </a>
            <div
                class="fi-drilldown-title"
                tabindex="-1"
                x-ref="title"
                @if ($collapsible) x-show="$store.sidebar.isOpen" @endif
            >{{ $level['title'] ?? $level['parent']->getLabel() }}</div>
        </div>
    @endif

    <ul class="fi-drilldown-list" :data-direction="direction">
        @foreach ($level['items'] as $item)
            @include('filament-drilldown-nav::item', ['item' => $item, 'ctx' => $ctx, 'nested' => false, 'first' => $loop->first, 'last' => $loop->last])
        @endforeach
    </ul>
</nav>

<style>
    .fi-drilldown-nav { display: flex; flex-direction: column; gap: 0.25rem; }
    .fi-drilldown-list { display: flex; flex-direction: column; gap: 0.25rem; margin: 0; padding: 0; list-style: none; }
    .fi-drilldown-list[data-direction="forward"] { animation: fi-drilldown-forward 0.2s ease-out; }
    .fi-drilldown-list[data-direction="back"] { animation: fi-drilldown-back 0.2s ease-out; }

    .fi-drilldown-header { display: flex; flex-direction: column; padding: 0 0.5rem 0.5rem; margin-bottom: 0.25rem; border-bottom: 1px solid var(--gray-200); }
    .dark .fi-drilldown-header { border-bottom-color: rgb(255 255 255 / 0.1); }
    .fi-drilldown-back { display: flex; align-items: center; gap: 0.25rem; margin-inline-start: -0.25rem; font-size: 0.875rem; font-weight: 500; color: var(--gray-500); text-decoration: none; }
    .fi-drilldown-back:hover { color: var(--gray-950); }
    .fi-drilldown-title { margin-top: 0.25rem; font-size: 1rem; font-weight: 600; color: var(--gray-950); outline: none; }
    .dark .fi-drilldown-back { color: var(--gray-400); }
    .dark .fi-drilldown-back:hover, .dark .fi-drilldown-title { color: #fff; }

    .fi-drilldown-heading { padding: 0.75rem 0.5rem 0.25rem; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: var(--gray-500); }
    .dark .fi-drilldown-heading { color: var(--gray-400); }
    .fi-drilldown-divider { height: 1px; margin: 0.5rem 0.5rem; background: var(--gray-200); list-style: none; }
    .dark .fi-drilldown-divider { background: rgb(255 255 255 / 0.1); }

    .fi-drilldown-inline-row { display: flex; align-items: center; gap: 0.125rem; }
    .fi-drilldown-inline-row > .fi-sidebar-item-btn { flex: 1 1 auto; min-width: 0; width: 100%; background: none; border: 0; cursor: pointer; text-align: start; }
    .fi-drilldown-toggle { display: inline-flex; align-items: center; justify-content: center; flex: none; width: 1.75rem; height: 1.75rem; border: 0; border-radius: 0.375rem; background: none; color: var(--gray-400); cursor: pointer; }
    .fi-drilldown-toggle:hover { background: var(--gray-100); color: var(--gray-700); }
    .dark .fi-drilldown-toggle:hover { background: rgb(255 255 255 / 0.05); color: var(--gray-200); }
    .fi-drilldown-toggle-wrap { display: inline-flex; margin-inline-start: auto; }
    .fi-drilldown-toggle-icon { width: 1rem; height: 1rem; flex: none; transition: transform 0.15s ease; }
    .fi-drilldown-toggle-icon.fi-open { transform: rotate(90deg); }
    [dir="rtl"] .fi-drilldown-toggle-icon { transform: scaleX(-1); }
    [dir="rtl"] .fi-drilldown-toggle-icon.fi-open { transform: scaleX(-1) rotate(90deg); }
    .fi-drilldown-inline-children { margin-top: 0.125rem; }
    .fi-drilldown-inline-children .fi-drilldown-inline-children { padding-inline-start: 0.75rem; }

    @keyframes fi-drilldown-forward { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: none; } }
    @keyframes fi-drilldown-back { from { opacity: 0; transform: translateX(-16px); } to { opacity: 1; transform: none; } }
    [dir="rtl"] .fi-drilldown-list[data-direction="forward"] { animation-name: fi-drilldown-back; }
    [dir="rtl"] .fi-drilldown-list[data-direction="back"] { animation-name: fi-drilldown-forward; }
    [dir="rtl"] .fi-drilldown-back > svg, [dir="rtl"] .fi-drilldown-chevron { transform: scaleX(-1); }

    @media (prefers-reduced-motion: reduce) {
        .fi-drilldown-list, .fi-drilldown-toggle-icon { animation: none !important; transition: none !important; }
    }
</style>
