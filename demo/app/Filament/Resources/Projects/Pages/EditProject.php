<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** The project's Settings → General page. */
class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        return $this->record->name.' — General settings';
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
