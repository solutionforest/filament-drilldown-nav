@php
    /** The "View all (N)" / "+N more" row that closes a shortened list (see NavItem::limit()). */
@endphp

@if (($hidden ?? 0) > 0)
    <li class="fi-drilldown-more">
        @if ($moreUrl)
            <a href="{{ $moreUrl }}" @if ($ctx['spa']) wire:navigate @endif>{{ __('filament-drilldown-nav::messages.view_all', ['count' => $hidden]) }}</a>
        @else
            <span>{{ __('filament-drilldown-nav::messages.more', ['count' => $hidden]) }}</span>
        @endif
    </li>
@endif
