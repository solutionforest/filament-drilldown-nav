<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DemoPage;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Site;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use SolutionForest\DrilldownNav\DrilldownNavPlugin;
use SolutionForest\DrilldownNav\Tree\NavItem;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->plugin(DrilldownNavPlugin::make()->items(fn () => [
                NavItem::page(Dashboard::class),

                // A Filament Resource. Inside one Site, the sidebar shows that Site's own menu.
                NavItem::resource(SiteResource::class)->record(fn (Site $site) => [
                    NavItem::make('Overview')->icon('heroicon-o-eye')->url(SiteResource::getUrl('view', ['record' => $site])),
                    NavItem::make('Pages')->icon('heroicon-o-document-text')->url(SiteResource::getUrl('section', ['record' => $site, 'section' => 'pages'])),
                    NavItem::make('Media')->icon('heroicon-o-photo')->url(SiteResource::getUrl('section', ['record' => $site, 'section' => 'media'])),
                    NavItem::make('Edit')->icon('heroicon-o-pencil-square')->url(SiteResource::getUrl('edit', ['record' => $site])),
                ]),

                // Static 4-level tree (custom pages / React pages are just URLs).
                NavItem::make('Docs')->icon('heroicon-o-book-open')->url('/admin/demo/docs')->children([
                    NavItem::make('Guides')->url('/admin/demo/docs/guides')->children([
                        NavItem::make('Getting started')->url('/admin/demo/docs/guides/start')->children([
                            NavItem::make('Install')->url('/admin/demo/docs/guides/start/install'),
                            NavItem::make('Configure')->url('/admin/demo/docs/guides/start/configure'),
                        ]),
                    ]),
                    NavItem::make('API')->url('/admin/demo/docs/api'),
                ]),

                // A pure group: no URL of its own, clicking it opens its first child.
                NavItem::make('Settings')->icon('heroicon-o-cog-6-tooth')->children([
                    NavItem::make('General')->url('/admin/demo/settings/general'),
                    NavItem::make('Team')->url('/admin/demo/settings/team'),
                ]),
            ]))
            ->pages([
                Dashboard::class,
                DemoPage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
