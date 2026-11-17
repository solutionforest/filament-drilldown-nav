<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Projects\ProjectResource;
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

/**
 * The near-zero-config path: the same resources as `admin`, with no hand-written record menus.
 *
 * - Projects: `->recordPages()` builds each project's menu from ProjectResource's own record pages.
 * - Clients: ClientResource implements HasDrilldownRecordMenu, so `NavItem::resource()` alone is enough.
 * - Everything else: Filament's own navigation, imported as is.
 */
class AutoPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('auto')
            ->path('auto')
            ->login()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->pages([Dashboard::class])
            ->plugin(DrilldownNavPlugin::make()->pollBadges('30s')->pinnable(database: true)->items(fn () => [
                NavItem::page(Dashboard::class),
                NavItem::resource(ClientResource::class),
                NavItem::resource(ProjectResource::class)->recordPages(),
                ...NavItem::fromFilamentNavigation(fn ($item) => ! in_array((string) $item->getLabel(), ['Dashboard', 'Clients', 'Projects'], true)),
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
