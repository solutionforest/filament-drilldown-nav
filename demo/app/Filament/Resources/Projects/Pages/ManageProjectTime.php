<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Tracked time for the project (staff only: hidden from client logins). */
class ManageProjectTime extends ManageRelatedRecords
{
    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'timeEntries';

    public static function canAccess(array $parameters = []): bool
    {
        return ! auth()->user()?->isClient();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('worked_on')->date()->sortable(),
                TextColumn::make('user.name')->label('Who'),
                TextColumn::make('minutes')->label('Minutes')->summarize(Sum::make()->label('Total')),
                TextColumn::make('note'),
            ])
            ->defaultSort('worked_on', 'desc');
    }
}
