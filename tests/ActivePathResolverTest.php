<?php

use SolutionForest\DrilldownNav\Tree\ActivePathResolver;
use SolutionForest\DrilldownNav\Tree\NavItem;

function labels(array $items): array
{
    return array_map(fn (NavItem $n) => (string) $n->getLabel(), $items);
}

function fourLevelTree(): array
{
    return [
        NavItem::make('Home')->url('/admin'),
        NavItem::make('Sites')->url('/admin/sites')->children([
            NavItem::make('Site 1')->url('/admin/sites/1')->children([
                NavItem::make('Pages')->url('/admin/sites/1/pages')->children([
                    NavItem::make('Page 9')->url('/admin/sites/1/pages/9'),
                    NavItem::make('Hidden')->url('/admin/sites/1/pages/x')->visible(false),
                ]),
            ]),
        ]),
    ];
}

function resolveNav(array $tree, string $path, ?string $root = '/admin'): array
{
    $r = new ActivePathResolver;
    $resolved = $r->resolve($tree, $path, $root);

    return [$resolved, $r->level($tree, $resolved, '/admin')];
}

it('resolves the deepest item on a four-level path', function () {
    [$resolved] = resolveNav(fourLevelTree(), '/admin/sites/1/pages/9');

    expect(labels($resolved['path']))->toBe(['Sites', 'Site 1', 'Pages', 'Page 9']);
});

it('shows the level of the deepest item that has children, with back target', function () {
    [, $level] = resolveNav(fourLevelTree(), '/admin/sites/1/pages/9');

    expect((string) $level['parent']->getLabel())->toBe('Pages')
        ->and($level['depth'])->toBe(3)
        ->and($level['back'])->toBe('/admin/sites/1')
        ->and(labels($level['items']))->toBe(['Page 9'])
        ->and((string) $level['active']->getLabel())->toBe('Page 9');
});

it('falls back to the root level for unknown urls, with nothing active', function () {
    [, $level] = resolveNav(fourLevelTree(), '/admin/unknown/thing');

    expect($level['parent'])->toBeNull()
        ->and($level['items'])->toHaveCount(2)
        ->and($level['active'])->toBeNull(); // bug 4: Dashboard must not light up
});

it('matches absolute Filament URLs by their path (bug 2)', function () {
    $tree = [NavItem::make('Posts')->url('http://localhost/admin/posts')->children([
        NavItem::make('Edit')->url('http://localhost/admin/posts/1/edit'),
    ])];

    [$resolved] = resolveNav($tree, '/admin/posts/1/edit');

    expect(labels($resolved['path']))->toBe(['Posts', 'Edit']);
});

it('matches the panel root exactly only', function () {
    [$resolved] = resolveNav(fourLevelTree(), '/admin');

    expect(labels($resolved['path']))->toBe(['Home']);
});

it('lets a URL-less group drill into its first child (bug 3)', function () {
    $tree = [
        NavItem::make('Content')->children([
            NavItem::make('Posts')->url('/admin/posts'),
            NavItem::make('Tags')->url('/admin/tags'),
        ]),
    ];

    expect($tree[0]->getLinkUrl())->toBe('/admin/posts');

    [, $level] = resolveNav($tree, '/admin/tags');

    expect((string) $level['parent']->getLabel())->toBe('Content')
        ->and($level['back'])->toBe('/admin'); // root group → panel home
});

it('back from a level under a URL-less group goes to that group\'s first child (bug 3)', function () {
    $tree = [
        NavItem::make('Content')->children([
            NavItem::make('Posts')->url('/admin/posts')->children([
                NavItem::make('Drafts')->url('/admin/posts/drafts'),
            ]),
        ]),
    ];

    [, $level] = resolveNav($tree, '/admin/posts/drafts');

    expect($level['back'])->toBe('/admin/posts');
});

it('runs each children closure at most once per request (bug 5)', function () {
    $calls = 0;
    $tree = [
        NavItem::make('Sites')->url('/admin/sites')->children(function () use (&$calls) {
            $calls++;

            return [NavItem::make('Site 1')->url('/admin/sites/1')];
        }),
    ];

    $r = new ActivePathResolver;
    $resolved = $r->resolve($tree, '/admin/sites/1', '/admin');
    $r->level($tree, $resolved, '/admin');
    $r->level($tree, $resolved, '/admin');

    expect($calls)->toBe(1);
});

it('does not open children of items that are not on the path (bug 5)', function () {
    $calls = 0;
    $tree = [
        NavItem::make('Other')->url('/admin/other')->children(function () use (&$calls) {
            $calls++;

            return [];
        }),
        NavItem::make('Sites')->url('/admin/sites'),
    ];

    resolveNav($tree, '/admin/sites');

    expect($calls)->toBe(0);
});

it('shows a per-record menu inside one record', function () {
    $sites = [1 => 'Acme', 2 => 'Globex'];

    $tree = [
        NavItem::make('Sites')->url('/admin/sites')
            ->resolveRecordUsing(fn (string $key) => $sites[$key] ?? null)
            ->record(fn (string $site) => [
                NavItem::make('Pages')->url('/admin/sites/'.array_search($site, $sites).'/pages'),
                NavItem::make('Media')->url('/admin/sites/'.array_search($site, $sites).'/media'),
            ]),
    ];

    [, $level] = resolveNav($tree, '/admin/sites/2/media');

    expect($level['title'])->toBe('Globex')
        ->and(labels($level['items']))->toBe(['Pages', 'Media'])
        ->and((string) $level['active']->getLabel())->toBe('Media')
        ->and($level['back'])->toBe('/admin/sites');

    // the list page, "create", and unknown records are not a record context
    foreach (['/admin/sites', '/admin/sites/create', '/admin/sites/99'] as $path) {
        [, $level] = resolveNav($tree, $path);
        expect($level['title'])->toBeNull();
    }
});
