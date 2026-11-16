<?php

namespace App\Filament\Resources\Agents\Pages;

use App\Filament\Resources\Agents\AgentResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/** Placeholder page for the agent's Playground, Websites, Settings → Advanced, ... */
class AgentSection extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AgentResource::class;

    protected string $view = 'filament.pages.agent-section';

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
