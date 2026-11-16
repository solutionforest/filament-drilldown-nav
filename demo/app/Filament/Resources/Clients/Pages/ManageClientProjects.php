<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The client's projects (a read-only table; each row opens the project and its own menu). */
class ManageClientProjects extends ManageRelatedRecords
{
    protected static string $resource = ClientResource::class;

    protected static string $relationship = 'projects';

    public static function getNavigationLabel(): string
    {
        return 'Projects';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('due_on')->date(),
                TextColumn::make('budget')->money('USD'),
            ])
            ->recordUrl(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make()->url(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record]))]);
    }
}
