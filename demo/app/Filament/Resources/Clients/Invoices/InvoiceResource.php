<?php

namespace App\Filament\Resources\Clients\Invoices;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Clients\Invoices\Pages\EditInvoice;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Nested under ClientResource: URLs are /admin/clients/{client}/invoices/... */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $parentResource = ClientResource::class;

    protected static ?string $slug = 'invoices';

    protected static ?string $recordTitleAttribute = 'number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->required(),
            TextInput::make('amount')->numeric()->prefix('$')->required(),
            Select::make('status')->options(['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue'])->required(),
            DatePicker::make('due_on'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('amount')->money('USD')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'paid' => 'success',
                    'overdue' => 'danger',
                    'sent' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('due_on')->date()->sortable(),
            ])
            ->defaultSort('due_on', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
