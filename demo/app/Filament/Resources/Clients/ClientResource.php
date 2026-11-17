<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\ClientSection;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ManageClientInvoices;
use App\Filament\Resources\Clients\Pages\ManageClientProjects;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use SolutionForest\DrilldownNav\Contracts\HasDrilldownRecordMenu;
use SolutionForest\DrilldownNav\Tree\NavItem;

class ClientResource extends Resource implements HasDrilldownRecordMenu
{
    protected static ?string $model = Client::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    /** Staff only: the client login never sees the client list. */
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasRole('admin', 'manager');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('industry'),
            Select::make('status')->options(['prospect' => 'Prospect', 'active' => 'Active', 'churned' => 'Churned'])->required()->default('active'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('industry'),
            TextEntry::make('status')->badge(),
            TextEntry::make('projects_count')->counts('projects')->label('Projects'),
            TextEntry::make('invoices_count')->counts('invoices')->label('Invoices'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('industry')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'prospect' => 'info',
                    default => 'gray',
                }),
                TextColumn::make('projects_count')->counts('projects')->label('Projects')->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([ViewAction::make()]);
    }

    /**
     * The menu inside one client, declared next to the resource. NavItem::resource(ClientResource::class)
     * picks it up with no extra config (the `auto` panel); the `admin` panel overrides it with ->record().
     */
    public static function drilldownRecordMenu(Model $record): array
    {
        return [
            NavItem::make('Overview')->icon('heroicon-o-eye')->url(static::getUrl('view', ['record' => $record])),
            NavItem::make('Projects')->icon('heroicon-o-rectangle-stack')->url(static::getUrl('projects', ['record' => $record])),
            NavItem::make('Invoices')->icon('heroicon-o-receipt-percent')->url(static::getUrl('invoices', ['record' => $record])),
            NavItem::make('Contacts')->icon('heroicon-o-users')->url(static::getUrl('section', ['record' => $record, 'section' => 'contacts'])),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'view' => ViewClient::route('/{record}'),
            'projects' => ManageClientProjects::route('/{record}/projects'),
            // Client-scoped resource (nested): /clients/{client}/invoices/...
            'invoices' => ManageClientInvoices::route('/{record}/invoices'),
            // Registered last so the routes above win.
            'section' => ClientSection::route('/{record}/{section}'),
        ];
    }
}
