<?php

namespace App\Filament\Pages\Reports;

use Filament\Pages\Page;

/**
 * Base for the demo's "Reports" pages. They are registered through Filament's NATIVE
 * navigation ($navigationGroup, $navigationParentItem, badges ...) and reach the drill-down
 * sidebar through NavItem::fromFilamentNavigation().
 */
abstract class ReportPage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.report';
}
