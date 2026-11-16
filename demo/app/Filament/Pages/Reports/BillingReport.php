<?php

namespace App\Filament\Pages\Reports;

class BillingReport extends ReportPage
{
    protected static ?string $navigationLabel = 'Billing';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reports/billing';

    /** Hidden from the menu (and the route) for everyone but the demo admin. */
    public static function canAccess(): bool
    {
        return auth()->user()?->email === 'demo@example.com';
    }
}
