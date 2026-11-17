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

    $pathIds = array_map(fn ($item) => spl_object_id($item), $resolved['path']);

    // What this level lists (shortened by ->limit() where set; the current page is never hidden).
    $display = $level['parent']
        ? $level['parent']->getDisplayChildren($pathIds)
        : ['items' => $level['items'], 'hidden' => 0, 'moreUrl' => null];

    // Search: every label on screen (lower-cased), so the "no matches" note needs no DOM counting.
    $searchable = $plugin->isSearchable();
    $collect = function (array $items) use (&$collect, $pathIds, $inlineByDefault): array {
        $labels = [];
        foreach ($items as $item) {
            if ($item->isHeading()) {
                continue;
            }
            $labels[] = mb_strtolower($item->getLabelText());
            if ($item->isInline($inlineByDefault) && $item->hasChildren()) {
                $labels = [...$labels, ...$collect($item->getDisplayChildren($pathIds)['items'])];
            }
        }

        return $labels;
    };
    $allLabels = $searchable ? $collect($display['items']) : [];
    $showSearch = $searchable && count($allLabels) >= $plugin->getSearchMinItems();

    // Recent / pinned shortcuts: the current page, as "Project › Tasks".
    $pinnable = $plugin->isPinnable();
    $recentLimit = $plugin->getRecentLimit();
    $currentTrail = collect($resolved['path'])->slice(-2)->map(fn ($item) => $item->getLabelText())->implode(' › ');
    $shortcuts = [
        'key' => $plugin->getStorageKey(),
        // Only entries under this panel's (and tenant's) URL are shown.
        'root' => ActivePathResolver::normalise($homeUrl),
        'recentLimit' => $recentLimit,
        'pinnable' => $pinnable,
        'label' => $resolved['path'] === [] ? null : $currentTrail,
    ];

    // Render hooks (see DrilldownNavHook): scoped to this panel, with the level's data.
    $hookScopes = [$plugin->getPanelId()];
    $hookData = ['plugin' => $plugin, 'panel' => $plugin->getPanelId(), 'depth' => $level['depth'], 'title' => $level['parent'] ? ($level['title'] ?? $level['parent']->getLabelText()) : null];
    // Hook content is wrapped (only when there is any) and, in a sidebar that collapses to icons, shown only
    // while it is open: free text would otherwise widen the icon rail.
    $hook = function (string $name) use ($hookScopes, $hookData, $collapsible): string {
        $html = trim(\Filament\Support\Facades\FilamentView::renderHook($name, scopes: $hookScopes, data: $hookData)->toHtml());

        if ($html === '') {
            return '';
        }

        return '<div class="fi-drilldown-hook" data-hook="'.e($name).'"'.($collapsible ? ' x-show="$store.sidebar.isOpen"' : '').'>'.$html.'</div>';
    };

    // Shared with the (recursive) item partial.
    $ctx = [
        'pathIds' => $pathIds,
        'searchable' => $searchable,
        'current' => $resolved['path'] === [] ? null : $resolved['path'][array_key_last($resolved['path'])],
        'inlineByDefault' => $inlineByDefault,
        'collapsible' => $collapsible,
        'spa' => $spa,
        // Open/closed state of inline sections, read on the server (cookie) so the first paint is right.
        'openState' => $plugin->getOpenState(),
        'accordion' => $plugin->isAccordion(),
        'panelId' => $plugin->getPanelId(),
    ];
@endphp

<nav
    x-data="{
        direction: 'none',
        query: '',
        labels: @js($allLabels),
        shortcuts: @js($shortcuts),
        pinned: [],
        recent: [],
        get searching() { return this.query.trim() !== '' },
        get noMatches() { const q = this.query.trim().toLowerCase(); return q !== '' && ! this.labels.some((label) => label.includes(q)) },
        matches(self, tail = '', ancestors = '') {
            const q = this.query.trim().toLowerCase()
            return q === '' || self.includes(q) || tail.includes(q) || ancestors.includes(q)
        },
        read(name) {
            let entries
            try { entries = JSON.parse(localStorage.getItem(this.shortcuts.key + ':' + name)) } catch (e) { return [] }
            if (! Array.isArray(entries)) { return [] }
            // Same-site paths under this panel only: drops entries from another panel, tenant or URL scheme.
            const root = this.shortcuts.root
            return entries.filter((entry) => entry && typeof entry.url === 'string' && typeof entry.label === 'string'
                && entry.url.startsWith('/') && ! entry.url.startsWith('//')
                && (root === '/' || entry.url === root || entry.url.startsWith(root + '/') || entry.url.startsWith(root + '?')))
        },
        write(name, value) { try { localStorage.setItem(this.shortcuts.key + ':' + name, JSON.stringify(value)) } catch (e) {} },
        get here() { return { url: location.pathname + location.search, label: this.shortcuts.label } },
        get isPinned() { return this.pinned.some((entry) => entry.url === this.here.url) },
        togglePin() {
            this.pinned = this.isPinned ? this.pinned.filter((entry) => entry.url !== this.here.url) : [this.here, ...this.pinned].slice(0, 20)
            this.write('pinned', this.pinned)
        },
        clearRecent() { this.recent = []; this.write('recent', []) },
        // Once a day per pinned page: drop pins whose page no longer opens (deleted, no access) and keep
        // the label of a renamed page current. A page we are on is refreshed without a request.
        async verifyPinned() {
            const now = Date.now()
            let checked = {}
            try { checked = JSON.parse(localStorage.getItem(this.shortcuts.key + ':checked')) ?? {} } catch (e) {}
            let changed = false
            for (const entry of [...this.pinned]) {
                if (entry.url === this.here.url) {
                    if (this.here.label && entry.label !== this.here.label) { entry.label = this.here.label; changed = true }
                    checked[entry.url] = now
                    continue
                }
                if (now - (checked[entry.url] ?? 0) < 86400000) { continue }
                try {
                    const response = await fetch(entry.url, { method: 'HEAD', credentials: 'same-origin', redirect: 'manual' })
                    if ([403, 404, 410].includes(response.status)) {
                        this.pinned = this.pinned.filter((pin) => pin.url !== entry.url)
                        delete checked[entry.url]
                        changed = true
                        continue
                    }
                    checked[entry.url] = now
                } catch (e) { /* offline: keep it */ }
            }
            if (changed) { this.write('pinned', this.pinned) }
            try { localStorage.setItem(this.shortcuts.key + ':checked', JSON.stringify(checked)) } catch (e) {}
        },
        init() {
            if (this.shortcuts.pinnable) {
                this.pinned = this.read('pinned')
                if (this.pinned.length) { (window.requestIdleCallback ?? ((callback) => setTimeout(callback, 1500)))(() => this.verifyPinned()) }
            }
            if (this.shortcuts.recentLimit > 0) {
                this.recent = this.read('recent')
                if (this.here.label) {
                    this.recent = [this.here, ...this.recent.filter((entry) => entry.url !== this.here.url)].slice(0, this.shortcuts.recentLimit)
                    this.write('recent', this.recent)
                }
            }

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
    {!! $hook(\SolutionForest\DrilldownNav\DrilldownNavHook::NAV_START) !!}

    @if ($showSearch)
        <div class="fi-drilldown-search" @if ($collapsible) x-show="$store.sidebar.isOpen" @endif>
            <input
                type="search"
                x-model="query"
                x-on:keydown.escape="query = ''"
                placeholder="{{ __('filament-drilldown-nav::messages.search') }}"
                aria-label="{{ __('filament-drilldown-nav::messages.search') }}"
                class="fi-drilldown-search-input"
            />
        </div>
    @endif

    @if ($level['parent'])
        {!! $hook(\SolutionForest\DrilldownNav\DrilldownNavHook::HEADER_BEFORE) !!}
    @endif

    @if ($level['parent'] && ($customHeader = $level['parent']->getHeader()) !== null)
        {{-- A header set with header() / recordHeader() replaces Back and the title; it gets the focus a title would. --}}
        <div class="fi-drilldown-custom-header" tabindex="-1" x-ref="title">{{ $customHeader }}</div>
    @elseif ($level['parent'])
        <div class="fi-drilldown-header">
            <a
                href="{{ $level['back'] ?? $homeUrl }}"
                @if ($spa) wire:navigate @endif
                @if ($collapsible)
                    x-bind:aria-label="$store.sidebar.isOpen ? null : @js(__('filament-drilldown-nav::messages.back'))"
                    {{-- Self-contained tooltip (the same directive Filament's own items use), so it does not depend on a Filament version's helper component. --}}
                    x-data="{ tooltip: false }"
                    x-effect="tooltip = $store.sidebar.isOpen ? false : { content: @js(__('filament-drilldown-nav::messages.back')), placement: document.dir === 'rtl' ? 'left' : 'right', theme: $store.theme }"
                    x-tooltip.html="tooltip"
                @endif
                class="fi-drilldown-back"
            >
                <x-filament::icon icon="heroicon-m-chevron-left" class="fi-drilldown-back-icon" />
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

    @if ($level['parent'])
        {!! $hook(\SolutionForest\DrilldownNav\DrilldownNavHook::HEADER_AFTER) !!}
    @endif

    {!! $hook(\SolutionForest\DrilldownNav\DrilldownNavHook::LIST_BEFORE) !!}

    <ul class="fi-drilldown-list" :data-direction="direction">
        @foreach ($display['items'] as $item)
            @include('filament-drilldown-nav::item', ['item' => $item, 'ctx' => $ctx, 'nested' => false, 'first' => $loop->first, 'last' => $loop->last, 'ancestors' => ''])
        @endforeach

        @include('filament-drilldown-nav::more', ['hidden' => $display['hidden'], 'moreUrl' => $display['moreUrl'], 'ctx' => $ctx])
    </ul>

    {!! $hook(\SolutionForest\DrilldownNav\DrilldownNavHook::LIST_AFTER) !!}

    @if ($searchable)
        <p class="fi-drilldown-empty" x-show="noMatches" x-cloak>{{ __('filament-drilldown-nav::messages.no_results') }}</p>
    @endif

    @if ($recentLimit > 0 || $pinnable)
        <div class="fi-drilldown-shortcuts" x-show="! searching" @if ($collapsible) x-show.important="$store.sidebar.isOpen" @endif>
            @if ($level['depth'] === 0)
                @if ($pinnable)
                    <template x-if="pinned.length">
                        <div>
                            <div class="fi-drilldown-heading">{{ __('filament-drilldown-nav::messages.pinned') }}</div>
                            <ul class="fi-drilldown-shortcut-list">
                                <template x-for="entry in pinned" :key="entry.url">
                                    <li><a :href="entry.url" @if ($spa) wire:navigate @endif class="fi-drilldown-shortcut" x-text="entry.label"></a></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                @endif
                @if ($recentLimit > 0)
                    <template x-if="recent.length">
                        <div>
                            <div class="fi-drilldown-heading fi-drilldown-heading-row">
                                <span>{{ __('filament-drilldown-nav::messages.recent') }}</span>
                                <button type="button" class="fi-drilldown-link" x-on:click="clearRecent()">{{ __('filament-drilldown-nav::messages.clear_recent') }}</button>
                            </div>
                            <ul class="fi-drilldown-shortcut-list">
                                <template x-for="entry in recent" :key="entry.url">
                                    <li><a :href="entry.url" @if ($spa) wire:navigate @endif class="fi-drilldown-shortcut" x-text="entry.label"></a></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                @endif
            @endif

            @if ($pinnable && $shortcuts['label'])
                <button type="button" class="fi-drilldown-link fi-drilldown-pin" x-on:click="togglePin()" x-bind:aria-pressed="isPinned.toString()">
                    <span x-show="! isPinned">{{ __('filament-drilldown-nav::messages.pin') }}</span>
                    <span x-show="isPinned" x-cloak>{{ __('filament-drilldown-nav::messages.unpin') }}</span>
                </button>
            @endif
        </div>
    @endif
    {!! $hook(\SolutionForest\DrilldownNav\DrilldownNavHook::NAV_END) !!}

    @if ($pollInterval = $plugin->getPollInterval())
        {{-- re-renders the sidebar component (badges, titles) on an interval; see DrilldownNavPlugin::pollBadges() --}}
        <span wire:poll.{{ $pollInterval }} class="fi-drilldown-poll" aria-hidden="true"></span>
    @endif
</nav>
