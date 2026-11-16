<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DemoPage;
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
use SolutionForest\DrilldownNav\Tree\NavNode;

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
            ->plugin(DrilldownNavPlugin::make()->tree(fn () => [
                NavNode::make('dashboard', 'Dashboard')->icon('heroicon-o-home')->url('/admin'),
                NavNode::make('sites', 'Sites')->icon('heroicon-o-globe-alt')->url('/admin/demo/sites')->children([
                    NavNode::make('acme', 'Acme Corp')->icon('heroicon-o-building-office')->url('/admin/demo/sites/acme')->children([
                        NavNode::make('pages', 'Pages')->icon('heroicon-o-document-text')->url('/admin/demo/sites/acme/pages')->children([
                            NavNode::make('home', 'Home page')->url('/admin/demo/sites/acme/pages/home'),
                            NavNode::make('about', 'About page')->url('/admin/demo/sites/acme/pages/about'),
                        ]),
                        NavNode::make('media', 'Media')->icon('heroicon-o-photo')->url('/admin/demo/sites/acme/media'),
                    ]),
                    NavNode::make('globex', 'Globex')->icon('heroicon-o-building-office')->url('/admin/demo/sites/globex'),
                ]),
                NavNode::make('settings', 'Settings')->icon('heroicon-o-cog-6-tooth')->url('/admin/demo/settings'),
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
