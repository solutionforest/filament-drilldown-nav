<?php

namespace App\Filament\Resources\Clients\Invoices\Pages;

use App\Filament\Resources\Clients\Invoices\InvoiceResource;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;
}
