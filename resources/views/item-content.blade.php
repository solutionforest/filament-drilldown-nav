@php
    /**
     * Icon, label and badge of an inline parent row (mirrors Filament's sidebar item, including
     * the icon-only collapsed mode).
     */
    $collapsible = $ctx['collapsible'];
    $isCurrent = $item === $ctx['current'];
    $icon = ($isCurrent && $item->getActiveIcon()) ? $item->getActiveIcon() : $item->getIcon();
@endphp

@if ($nested && blank($icon))
    {{-- the same guide line + dot Filament draws for nested, icon-less items --}}
    <div class="fi-sidebar-item-grouped-border">
        @if (! $first)
            <div class="fi-sidebar-item-grouped-border-part-not-first"></div>
        @endif

        @if (! $last)
            <div class="fi-sidebar-item-grouped-border-part-not-last"></div>
        @endif

        <div class="fi-sidebar-item-grouped-border-part"></div>
    </div>
@endif

@if (filled($icon))
    {{ \Filament\Support\generate_icon_html($icon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large) }}
@endif

<span @if ($collapsible) x-show="$store.sidebar.isOpen" @endif class="fi-sidebar-item-label">{{ $item->getLabel() }}</span>

@if (filled($item->getBadge()))
    <span @if ($collapsible) x-show="$store.sidebar.isOpen" @endif class="fi-sidebar-item-badge-ctn">
        <x-filament::badge :color="$item->getBadgeColor()">{{ $item->getBadge() }}</x-filament::badge>
    </span>
@endif
