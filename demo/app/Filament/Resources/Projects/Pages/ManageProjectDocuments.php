<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\Documents\DocumentResource;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

/** Files → Documents: the project's documents, a normal Filament table of a nested resource. */
class ManageProjectDocuments extends ManageRelatedRecords
{
    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'documents';

    protected static ?string $relatedResource = DocumentResource::class;

    public function table(Table $table): Table
    {
        return $table->headerActions([CreateAction::make()]);
    }
}
