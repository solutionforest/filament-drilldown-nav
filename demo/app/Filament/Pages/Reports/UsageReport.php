<?php

namespace App\Filament\Pages\Reports;

class UsageReport extends ReportPage
{
    protected static ?string $navigationLabel = 'Usage';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'reports/usage';

    public static function getNavigationBadge(): ?string
    {
        return '12';
    }
}
