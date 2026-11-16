<?php

namespace App\Filament\Resources\Projects\Documents;

use App\Filament\Resources\Projects\Documents\Pages\CreateDocument;
use App\Filament\Resources\Projects\Documents\Pages\EditDocument;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Nested under ProjectResource: URLs are /admin/projects/{project}/documents/... */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $parentResource = ProjectResource::class;

    protected static ?string $slug = 'documents';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('title')->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('title')->searchable()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
