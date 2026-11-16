<?php

namespace App\Filament\Resources\Clients\Invoices\Pages;

use App\Filament\Resources\Clients\Invoices\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
}
