<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Invoices\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

/** Invoices of one client: a normal Filament table of a nested resource. */
class ManageClientInvoices extends ManageRelatedRecords
{
    protected static string $resource = ClientResource::class;

    protected static string $relationship = 'invoices';

    protected static ?string $relatedResource = InvoiceResource::class;

    public function table(Table $table): Table
    {
        return $table->headerActions([CreateAction::make()]);
    }
}
