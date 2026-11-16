<?php

namespace App\Filament\Resources\Agents\Pages;

use App\Filament\Resources\Agents\AgentResource;
use App\Filament\Resources\Agents\Documents\DocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

/** Knowledge → Documents: the agent's documents, a normal Filament table of a nested resource. */
class ManageAgentDocuments extends ManageRelatedRecords
{
    protected static string $resource = AgentResource::class;

    protected static string $relationship = 'documents';

    protected static ?string $relatedResource = DocumentResource::class;

    public function table(Table $table): Table
    {
        return $table->headerActions([CreateAction::make()]);
    }
}
