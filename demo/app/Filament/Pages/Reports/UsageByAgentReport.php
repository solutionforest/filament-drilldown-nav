<?php

namespace App\Filament\Pages\Reports;

class UsageByAgentReport extends ReportPage
{
    protected static ?string $navigationLabel = 'By agent';

    // Native "parent item": shown as a child of "Usage" (drill-down opens a sub level).
    protected static ?string $navigationParentItem = 'Usage';

    protected static ?string $slug = 'reports/usage/by-agent';
}
