<x-filament-panels::page>
    <x-filament::section>
        <p>Page <strong>{{ $section }}</strong> of <strong>{{ $record->name ?? $record->title }}</strong>. In a real app this could be any Filament page, or a React page served under the same URL prefix.</p>
    </x-filament::section>
</x-filament-panels::page>
