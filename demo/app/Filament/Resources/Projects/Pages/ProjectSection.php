<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/** Placeholder page for the project's Tasks, Links, Settings → Advanced, ... */
class ProjectSection extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.pages.project-section';

    public string $section = '';

    public function mount(int|string $record, string $section = ''): void
    {
        $this->record = $this->resolveRecord($record);
        $this->section = $section;
    }

    public function getTitle(): string
    {
        return $this->record->name.' — '.ucfirst($this->section);
    }
}
