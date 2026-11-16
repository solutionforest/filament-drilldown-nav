<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DemoPage;
use App\Filament\Resources\Projects\Documents\DocumentResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Http\Middleware\SetLocale;
use App\Models\Project;
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
            ->sidebarCollapsibleOnDesktop()
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

                // Projects: a normal Filament table. Click an project and the sidebar shows
                // that project's own menu; Files and Settings open a sub menu.
                NavItem::resource(ProjectResource::class)->record(fn (Project $project) => [
                    NavItem::make('Overview')->icon('heroicon-o-eye')
                        ->url(ProjectResource::getUrl('view', ['record' => $project])),
                    NavItem::make('Tasks')->icon('heroicon-o-clipboard-document-check')
                        ->url(ProjectResource::getUrl('section', ['record' => $project, 'section' => 'tasks'])),
                    NavItem::make('Files')->icon('heroicon-o-book-open')->children([
                        // A nested resource, scoped to this project.
                        NavItem::resource(DocumentResource::class, ['project' => $project]),
                        NavItem::make('Links')->icon('heroicon-o-globe-alt')
                            ->url(ProjectResource::getUrl('section', ['record' => $project, 'section' => 'links'])),
                    ]),
                    NavItem::make('Settings')->icon('heroicon-o-cog-6-tooth')->children([
                        NavItem::make('General')->url(ProjectResource::getUrl('edit', ['record' => $project])),
                        NavItem::make('Advanced')->url(ProjectResource::getUrl('settings', ['record' => $project, 'section' => 'advanced'])),
                    ]),
                ]),

                // Reuse Filament's own navigation: pages/resources that set $navigationGroup,
                // $navigationParentItem, badges or canAccess() show up here without being
                // listed again (the Reports group). Items handled by hand above are filtered out.
                ...NavItem::fromFilamentNavigation(fn (NavigationItem $item): bool => ! in_array($item->getUrl(), [
                    Dashboard::getUrl(),
                    ProjectResource::getUrl('index'),
                ], true)),

                // Modern, click-to-expand sections: children open in place with a guide line
                // (->inline()), can nest, remember their state, and sit under a heading + divider.
                NavItem::heading('Workspace')->divider(),
                NavItem::make('Team')->icon('heroicon-o-users')->inline()->defaultExpanded()->children([
                    NavItem::make('Members')->url('/admin/demo/team/members')->badge('8'),
                    NavItem::make('Roles')->url('/admin/demo/team/roles'),
                    NavItem::make('Invitations')->url('/admin/demo/team/invitations')->badge('2', 'warning'),
                ]),
                NavItem::make('Subscription')->icon('heroicon-o-credit-card')->inline()->children([
                    NavItem::make('Overview')->url('/admin/demo/subscription/overview'),
                    NavItem::make('Invoices')->inline()->children([
                        NavItem::make('Paid')->url('/admin/demo/subscription/invoices/paid'),
                        NavItem::make('Unpaid')->url('/admin/demo/subscription/invoices/unpaid')->badge('3', 'danger'),
                    ]),
                    NavItem::make('Plans')->url('/admin/demo/subscription/plans'),
                ]),

                // Visible to the demo admin only (evaluated per request, per user).
                NavItem::make('Audit log')->icon('heroicon-o-shield-check')->url('/admin/demo/audit')
                    ->visible(fn (): bool => auth()->user()?->email === 'demo@example.com'),

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
                SetLocale::class,
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
