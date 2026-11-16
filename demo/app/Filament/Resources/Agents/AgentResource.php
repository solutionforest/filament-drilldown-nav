<?php

namespace App\Filament\Resources\Agents;

use App\Filament\Resources\Agents\Pages\AgentSection;
use App\Filament\Resources\Agents\Pages\CreateAgent;
use App\Filament\Resources\Agents\Pages\EditAgent;
use App\Filament\Resources\Agents\Pages\ListAgents;
use App\Filament\Resources\Agents\Pages\ManageAgentDocuments;
use App\Filament\Resources\Agents\Pages\ViewAgent;
use App\Models\Agent;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('model')->required()->default('claude-sonnet-5-5'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('model'),
            TextEntry::make('documents_count')->counts('documents')->label('Documents'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('model'),
                TextColumn::make('documents_count')->counts('documents')->label('Documents'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgents::route('/'),
            'create' => CreateAgent::route('/create'),
            'view' => ViewAgent::route('/{record}'),
            'edit' => EditAgent::route('/{record}/settings/general'),
            // Agent-scoped resource (nested): /agents/{agent}/documents/...
            'documents' => ManageAgentDocuments::route('/{record}/documents'),
            // Simple demo pages (registered last so the routes above win).
            'settings' => AgentSection::route('/{record}/settings/{section}'),
            'section' => AgentSection::route('/{record}/{section}'),
        ];
    }
}
