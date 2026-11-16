<?php

namespace App\Filament\Resources\Agents\Pages;

use App\Filament\Resources\Agents\AgentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** The agent's Settings → General page. */
class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    public function getTitle(): string
    {
        return $this->record->name.' — General settings';
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
