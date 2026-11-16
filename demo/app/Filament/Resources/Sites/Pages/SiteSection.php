<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\SiteResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/** Demo page for /sites/{site}/{section} (Pages, Media, ...). */
class SiteSection extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SiteResource::class;

    protected string $view = 'filament.pages.site-section';

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
