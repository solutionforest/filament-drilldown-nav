<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ManageProjectDocuments;
use App\Filament\Resources\Projects\Pages\ManageProjectTasks;
use App\Filament\Resources\Projects\Pages\ManageProjectTime;
use App\Filament\Resources\Projects\Pages\ProjectSection;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Select::make('client_id')->relationship('client', 'name')->searchable()->preload(),
            Select::make('status')->options(['active' => 'Active', 'on hold' => 'On hold', 'completed' => 'Completed'])->required()->default('active'),
            DatePicker::make('due_on'),
            TextInput::make('budget')->numeric()->prefix('$')->default(0),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('client.name')->label('Client'),
            TextEntry::make('status')->badge(),
            TextEntry::make('due_on')->date(),
            TextEntry::make('budget')->money('USD'),
            TextEntry::make('tasks_count')->counts('tasks')->label('Tasks'),
            TextEntry::make('documents_count')->counts('documents')->label('Documents'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('client.name')->label('Client')->sortable()->visible(fn (): bool => ! auth()->user()?->isClient()),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'completed' => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('tasks_count')->counts('tasks')->label('Tasks')->sortable(),
                TextColumn::make('due_on')->date()->sortable(),
                TextColumn::make('budget')->money('USD')->sortable()->visible(fn (): bool => ! auth()->user()?->isClient()),
            ])
            ->defaultSort('due_on')
            ->recordActions([ViewAction::make(), EditAction::make()->visible(fn (): bool => ! auth()->user()?->isClient())]);
    }

    /** A client login only ever sees its own client's projects. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user?->isClient() ? $query->where('client_id', $user->client_id) : $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/settings/general'),
            // Project-scoped resource (nested): /projects/{project}/documents/...
            'documents' => ManageProjectDocuments::route('/{record}/documents'),
            'tasks' => ManageProjectTasks::route('/{record}/tasks'),
            'time' => ManageProjectTime::route('/{record}/time'),
            // Simple demo pages (registered last so the routes above win).
            'settings' => ProjectSection::route('/{record}/settings/{section}'),
            'section' => ProjectSection::route('/{record}/{section}'),
        ];
    }
}
