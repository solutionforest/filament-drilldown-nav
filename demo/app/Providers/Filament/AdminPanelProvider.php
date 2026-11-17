<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DemoPage;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Invoices\InvoiceResource;
use App\Filament\Resources\Projects\Documents\DocumentResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Tasks\TaskResource;
use App\Http\Middleware\SetLocale;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
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
            ->plugin(DrilldownNavPlugin::make()
                ->searchable(minItems: 6)   // filter box once a level lists 6+ items
                ->recent(5)                 // "Recent" pages at the top level
                ->pinnable()                // "Pin this page" + a "Pinned" list
                ->items(fn () => [
                    NavItem::page(Dashboard::class),

                    // Clients (staff only). Click a client: its own menu. "Projects" opens a level listing
                    // that client's projects; "Invoices" is a nested resource with an unpaid-count badge.
                    NavItem::resource(ClientResource::class)->record(fn (Client $client) => [
                        NavItem::make('Overview')->icon('heroicon-o-eye')
                            ->url(ClientResource::getUrl('view', ['record' => $client])),
                        NavItem::make('Projects')->icon('heroicon-o-rectangle-stack')
                            ->url(ClientResource::getUrl('projects', ['record' => $client]))
                            ->badge(fn (): string => (string) $client->projects()->count())
                            ->children(fn (): array => $client->projects()->latest('due_on')->limit(40)->get()
                                ->map(fn (Project $project): NavItem => NavItem::make($project->name)
                                    ->url(ProjectResource::getUrl('view', ['record' => $project])))
                                ->all())
                            // long lists stay short: first 5, then "View all (N)"
                            ->limit(5, ClientResource::getUrl('projects', ['record' => $client])),
                        NavItem::resource(InvoiceResource::class, ['client' => $client])
                            ->icon('heroicon-o-receipt-percent')
                            ->badge(fn (): string => (string) $client->invoices()->whereIn('status', ['sent', 'overdue'])->count(), 'warning'),
                        NavItem::make('Contacts')->icon('heroicon-o-users')
                            ->url(ClientResource::getUrl('section', ['record' => $client, 'section' => 'contacts'])),
                    ]),

                    // Projects: a normal Filament table. Click a project: its own menu (5 levels deep:
                    // Projects > Project > Tasks > Task > Comments). Client logins only see their own projects,
                    // and never Time or Settings.
                    NavItem::resource(ProjectResource::class)->record(fn (Project $project) => [
                        NavItem::make('Overview')->icon('heroicon-o-eye')
                            ->url(ProjectResource::getUrl('view', ['record' => $project])),
                        NavItem::resource(TaskResource::class, ['project' => $project])
                            ->badge(fn (): string => (string) $project->tasks()->where('status', '!=', 'done')->count())
                            ->record(fn (Task $task) => [
                                NavItem::make('Details')->icon('heroicon-o-document-text')
                                    ->url(TaskResource::getUrl('edit', ['project' => $project, 'record' => $task])),
                                NavItem::make('Comments')->icon('heroicon-o-chat-bubble-left-right')
                                    ->url(TaskResource::getUrl('section', ['project' => $project, 'record' => $task, 'section' => 'comments'])),
                                NavItem::make('History')->icon('heroicon-o-clock')
                                    ->url(TaskResource::getUrl('section', ['project' => $project, 'record' => $task, 'section' => 'history'])),
                            ]),
                        NavItem::make('Time')->icon('heroicon-o-clock')
                            ->url(ProjectResource::getUrl('time', ['record' => $project]))
                            ->visible(fn (): bool => ! auth()->user()?->isClient()),
                        NavItem::make('Files')->icon('heroicon-o-book-open')->children([
                            // A nested resource, scoped to this project.
                            NavItem::resource(DocumentResource::class, ['project' => $project]),
                            NavItem::make('Links')->icon('heroicon-o-globe-alt')
                                ->url(ProjectResource::getUrl('section', ['record' => $project, 'section' => 'links'])),
                        ]),
                        NavItem::make('Settings')->icon('heroicon-o-cog-6-tooth')->children([
                            NavItem::make('General')->url(ProjectResource::getUrl('edit', ['record' => $project])),
                            NavItem::make('Advanced')->url(ProjectResource::getUrl('settings', ['record' => $project, 'section' => 'advanced'])),
                        ])->visible(fn (): bool => ! auth()->user()?->isClient()),
                    ]),

                    // Reuse Filament's own navigation: pages/resources that set $navigationGroup,
                    // $navigationParentItem, badges or canAccess() show up here without being
                    // listed again (the Reports group). Items handled by hand above are filtered out.
                    ...NavItem::fromFilamentNavigation(fn (NavigationItem $item): bool => ! in_array($item->getUrl(), [
                        Dashboard::getUrl(),
                        ProjectResource::getUrl('index'),
                        ClientResource::getUrl('index'),
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
