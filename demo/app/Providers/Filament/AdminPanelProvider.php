<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DemoPage;
use App\Filament\Resources\Agents\AgentResource;
use App\Filament\Resources\Agents\Documents\DocumentResource;
use App\Models\Agent;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
            ->spa((bool) env('DEMO_SPA', false))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigationGroups([
                NavigationGroup::make('Reports')->icon('heroicon-o-chart-pie'),
            ])
            ->plugin(DrilldownNavPlugin::make()->items(fn () => [
                NavItem::page(Dashboard::class),

                // Agents: a normal Filament table. Click an agent and the sidebar shows
                // that agent's own menu; Knowledge and Settings open a sub menu.
                NavItem::resource(AgentResource::class)->record(fn (Agent $agent) => [
                    NavItem::make('Overview')->icon('heroicon-o-eye')
                        ->url(AgentResource::getUrl('view', ['record' => $agent])),
                    NavItem::make('Playground')->icon('heroicon-o-chat-bubble-left-right')
                        ->url(AgentResource::getUrl('section', ['record' => $agent, 'section' => 'playground'])),
                    NavItem::make('Knowledge')->icon('heroicon-o-book-open')->children([
                        // A nested resource, scoped to this agent.
                        NavItem::resource(DocumentResource::class, ['agent' => $agent]),
                        NavItem::make('Websites')->icon('heroicon-o-globe-alt')
                            ->url(AgentResource::getUrl('section', ['record' => $agent, 'section' => 'websites'])),
                    ]),
                    NavItem::make('Settings')->icon('heroicon-o-cog-6-tooth')->children([
                        NavItem::make('General')->url(AgentResource::getUrl('edit', ['record' => $agent])),
                        NavItem::make('Advanced')->url(AgentResource::getUrl('settings', ['record' => $agent, 'section' => 'advanced'])),
                    ]),
                ]),

                // Reuse Filament's own navigation: pages/resources that set $navigationGroup,
                // $navigationParentItem, badges or canAccess() show up here without being
                // listed again (the Reports group). Items handled by hand above are filtered out.
                ...NavItem::fromFilamentNavigation(fn (NavigationItem $item): bool => ! in_array($item->getUrl(), [
                    Dashboard::getUrl(),
                    AgentResource::getUrl('index'),
                ], true)),

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
