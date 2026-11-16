# Filament Drilldown Nav

Drill-down (slide) multi-level sidebar navigation for Filament v5. One left sidebar shows one level at a time; clicking an item with children slides into its own menu, with a Back link. Works at any depth.

> Status: phase-1 spike. Core tree + active-path resolver are tested; the Blade sidebar is not yet verified in a running panel and has no slide animation yet.

```php
->plugin(
    DrilldownNavPlugin::make()->tree(fn () => [
        NavNode::make('sites', 'Sites')->url('/admin/sites')->icon('heroicon-o-globe-alt')->children([...]),
    ])
)
```
