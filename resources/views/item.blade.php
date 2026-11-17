@php
    /**
     * One menu entry. Recursive for items that expand in place.
     *
     * @var \SolutionForest\DrilldownNav\Tree\NavItem $item
     * @var array{pathIds: array<int, int>, current: ?\SolutionForest\DrilldownNav\Tree\NavItem, inlineByDefault: bool, collapsible: bool, spa: bool} $ctx
     * @var bool $nested  true when rendered inside an inline parent (guide-line style)
     */
    $collapsible = $ctx['collapsible'];
    $isCurrent = $item === $ctx['current'];
    $onPath = in_array(spl_object_id($item), $ctx['pathIds'], true);
    $inline = $item->isInline($ctx['inlineByDefault']) && $item->hasChildren();
    $labelText = $item->getLabelText();
    $ancestors = $ancestors ?? '';
    $lower = mb_strtolower($labelText);
    $tailText = '';

    if ($ctx['searchable']) {
        $descend = function (array $children) use (&$descend): string {
            return collect($children)->reject(fn ($child) => $child->isHeading())
                ->map(fn ($child) => mb_strtolower($child->getLabelText()).' '.$descend($child->getChildren()))
                ->implode(' ');
        };
        $tailText = $descend($item->getChildren());
    }

    $searchAttrs = new \Illuminate\View\ComponentAttributeBag($ctx['searchable']
        ? ['x-show' => 'matches('.\Illuminate\Support\Js::from($lower).', '.\Illuminate\Support\Js::from($tailText).', '.\Illuminate\Support\Js::from($ancestors).')']
        : []);
    $headingShow = collect([$collapsible ? '$store.sidebar.isOpen' : null, $ctx['searchable'] ? '! searching' : null])->filter()->implode(' && ');
@endphp

@if ($item->isHeading())
    @if ($item->hasDivider() && ! $first)
        <li role="separator" class="fi-drilldown-divider" @if ($ctx['searchable']) x-show="! searching" @endif></li>
    @endif
    <li class="fi-drilldown-heading" @if ($headingShow !== '') x-show="{{ $headingShow }}" @endif>{{ $item->getLabel() }}</li>
@else
    @if ($item->hasDivider() && ! $first)
        <li role="separator" class="fi-drilldown-divider" @if ($ctx['searchable']) x-show="! searching" @endif></li>
    @endif

    @if ($inline)
        @php
            $key = 'fi-drilldown-open:'.md5($labelText.'|'.$item->getUrl());
            $url = $item->getUrl();
            $panelOpen = $collapsible ? '$store.sidebar.isOpen' : 'true';
            $shown = $nested ? 'open' : 'open && '.$panelOpen;
        @endphp
        <li
            class="fi-sidebar-item fi-drilldown-inline @if ($isCurrent) fi-active @endif @if ($onPath && ! $isCurrent) fi-sidebar-item-has-active-child-items @endif"
            x-data="{
                open: {{ $onPath ? 'true' : 'false' }} || (localStorage.getItem(@js($key)) ?? @js($item->isDefaultExpanded() ? '1' : '0')) === '1',
            }"
            x-init="$watch('open', value => localStorage.setItem(@js($key), value ? '1' : '0'))"
            {{ $searchAttrs }}
        >
            <div class="fi-drilldown-inline-row">
                @if ($url)
                    <a
                        href="{{ $item->getLinkUrl() }}"
                        @if ($ctx['spa']) wire:navigate @endif
                        @if ($isCurrent) aria-current="page" @endif
                        class="fi-sidebar-item-btn"
                    >
                        @include('filament-drilldown-nav::item-content', ['item' => $item, 'ctx' => $ctx, 'nested' => $nested, 'first' => $first, 'last' => $last])
                    </a>
                    <button
                        type="button"
                        class="fi-drilldown-toggle"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open.toString()"
                        aria-label="{{ $labelText }}"
                        @if ($collapsible) x-show="$store.sidebar.isOpen" @endif
                    >
                        <x-filament::icon icon="heroicon-m-chevron-right" class="fi-drilldown-toggle-icon" x-bind:class="{ 'fi-open': open }" />
                    </button>
                @else
                    <button
                        type="button"
                        class="fi-sidebar-item-btn"
                        x-on:click="@if ($collapsible) $store.sidebar.isOpen ? (open = ! open) : $store.sidebar.open() @else open = ! open @endif"
                        x-bind:aria-expanded="open.toString()"
                    >
                        @include('filament-drilldown-nav::item-content', ['item' => $item, 'ctx' => $ctx, 'nested' => $nested, 'first' => $first, 'last' => $last])
                        <span class="fi-drilldown-toggle-wrap" @if ($collapsible) x-show="$store.sidebar.isOpen" @endif>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="fi-drilldown-toggle-icon" x-bind:class="{ 'fi-open': open }" />
                        </span>
                    </button>
                @endif
            </div>

            @php $displayed = $item->getDisplayChildren($ctx['pathIds']); @endphp
            <ul x-show="({{ $shown }}){{ $ctx['searchable'] ? ' || searching' : '' }}" x-collapse class="fi-sidebar-sub-group-items fi-drilldown-inline-children">
                @foreach ($displayed['items'] as $child)
                    @include('filament-drilldown-nav::item', ['item' => $child, 'ctx' => $ctx, 'nested' => true, 'first' => $loop->first, 'last' => $loop->last, 'ancestors' => trim($ancestors.' '.$lower)])
                @endforeach

                @include('filament-drilldown-nav::more', ['hidden' => $displayed['hidden'], 'moreUrl' => $displayed['moreUrl'], 'ctx' => $ctx])
            </ul>
        </li>
    @else
        <x-filament-panels::sidebar.item
            :active="$isCurrent"
            :active-child-items="$onPath && ! $isCurrent"
            :active-icon="$item->getActiveIcon()"
            :icon="$item->getIcon()"
            :url="$item->getLinkUrl()"
            :badge="$item->getBadge()"
            :badge-color="$item->getBadgeColor()"
            :grouped="$nested"
            :sub-grouped="$nested"
            :first="$first"
            :last="$last"
            :attributes="$searchAttrs"
        >
            {{ $item->getLabel() }}
            @if ($item->hasChildren() || $item->hasRecordMenu())
                <x-filament::icon icon="heroicon-m-chevron-right" class="fi-drilldown-chevron" style="width:1rem;height:1rem;display:inline-block;margin-inline-start:0.5rem;vertical-align:middle;opacity:.5" />
            @endif
        </x-filament-panels::sidebar.item>
    @endif
@endif
