<?php

namespace App\Filament\Resources\Projects\Tasks;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Tasks\Pages\CreateTask;
use App\Filament\Resources\Projects\Tasks\Pages\EditTask;
use App\Filament\Resources\Projects\Tasks\Pages\TaskSection;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Nested under ProjectResource: URLs are /admin/projects/{project}/tasks/... and each task has its own menu. */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $parentResource = ProjectResource::class;

    protected static ?string $slug = 'tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            Select::make('status')->options(self::STATUSES)->required()->default('todo'),
            Select::make('priority')->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'])->required()->default('normal'),
            Select::make('assignee_id')->relationship('assignee', 'name'),
            DatePicker::make('due_on'),
        ]);
    }

    public const STATUSES = ['todo' => 'To do', 'doing' => 'In progress', 'review' => 'In review', 'done' => 'Done'];

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success',
                        'review' => 'info',
                        'doing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('priority')->badge()->color(fn (string $state): string => $state === 'high' ? 'danger' : 'gray'),
                TextColumn::make('assignee.name')->label('Assignee'),
                TextColumn::make('due_on')->date()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(self::STATUSES)])
            ->defaultSort('due_on')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateTask::route('/create'),
            'edit' => EditTask::route('/{record}/edit'),
            // Registered last so /{record}/edit keeps winning: Comments, History, ...
            'section' => TaskSection::route('/{record}/{section}'),
        ];
    }
}
