{{-- A custom header for one task's menu (NavItem::recordHeader()): the way back, the task and its status. --}}
@php
    /** @var \App\Models\Task $task */
    $statusColor = match ($task->status) {
        'done' => 'success',
        'review' => 'warning',
        'doing' => 'info',
        default => 'gray',
    };
@endphp

<div class="fi-drilldown-header">
    <a href="{{ $backUrl }}" class="fi-drilldown-back">
        <x-filament::icon icon="heroicon-m-chevron-left" style="width:1.25rem;height:1.25rem" />
        <span>All tasks</span>
    </a>
    <div class="fi-drilldown-title">{{ $task->title }}</div>
    <div style="margin-top:0.375rem">
        <x-filament::badge :color="$statusColor" size="sm">{{ str($task->status)->headline() }}</x-filament::badge>
    </div>
</div>
