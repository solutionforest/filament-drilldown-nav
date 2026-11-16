<?php

namespace App\Filament\Resources\Projects\Tasks\Pages;

use App\Filament\Resources\Projects\Tasks\TaskResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/** Placeholder for a task's Comments, History, ... */
class TaskSection extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TaskResource::class;

    protected string $view = 'filament.pages.record-section';

    public string $section = '';

    public function mount(int|string $record, string $section = ''): void
    {
        $this->record = $this->resolveRecord($record);
        $this->section = $section;
    }

    public function getTitle(): string
    {
        return $this->record->title.' — '.ucfirst($this->section);
    }
}
