<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/** Placeholder page for a client's Contacts, Notes, ... */
class ClientSection extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ClientResource::class;

    protected string $view = 'filament.pages.record-section';

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
