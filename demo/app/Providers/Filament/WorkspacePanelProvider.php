<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DemoPage;
use App\Models\Team;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use SolutionForest\DrilldownNav\DrilldownNavPlugin;
use SolutionForest\DrilldownNav\Tree\NavItem;

/** Tenancy (/app/{team}) on its own domain: URLs the plugin must not assume. */
class WorkspacePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('workspace')
            ->domain('workspace.localhost')
            ->path('app')
            ->login()
            ->tenant(Team::class, slugAttribute: 'slug')
            ->pages([Dashboard::class, DemoPage::class])
            ->plugin(DrilldownNavPlugin::make()->items(fn () => [
                NavItem::page(Dashboard::class),
                NavItem::make('Billing')->url(DemoPage::getUrl(['path' => 'billing']))->children([
                    NavItem::make('Invoices')->url(DemoPage::getUrl(['path' => 'billing/invoices'])),
                    NavItem::make('Plans')->url(DemoPage::getUrl(['path' => 'billing/plans'])),
                ]),
            ]))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
