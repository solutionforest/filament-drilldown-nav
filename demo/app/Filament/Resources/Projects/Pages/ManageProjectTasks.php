<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Tasks\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

/** The project's tasks: a normal Filament table of a nested resource; every task has its own menu. */
class ManageProjectTasks extends ManageRelatedRecords
{
    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'tasks';

    protected static ?string $relatedResource = TaskResource::class;

    public function table(Table $table): Table
    {
        return $table->headerActions([CreateAction::make()]);
    }
}
