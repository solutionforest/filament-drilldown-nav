<?php

use SolutionForest\DrilldownNav\Tree\ActivePathResolver;
use SolutionForest\DrilldownNav\Tree\NavNode;

function fourLevelTree(): array
{
    return [
        NavNode::make('home', 'Home')->url('/admin'),
        NavNode::make('sites', 'Sites')->url('/admin/sites')->children([
            NavNode::make('site-1', 'Site 1')->url('/admin/sites/1')->children([
                NavNode::make('pages', 'Pages')->url('/admin/sites/1/pages')->children([
                    NavNode::make('page-9', 'Page 9')->url('/admin/sites/1/pages/9'),
                    NavNode::make('hidden', 'Hidden')->url('/admin/sites/1/pages/x')->visible(false),
                ]),
            ]),
        ]),
    ];
}

it('resolves the deepest node on a four-level path', function () {
    $path = (new ActivePathResolver)->resolve(fourLevelTree(), '/admin/sites/1/pages/9');

    expect(array_map(fn ($n) => $n->getId(), $path))->toBe(['sites', 'site-1', 'pages', 'page-9']);
});

it('shows the level of the deepest node that has children', function () {
    $r = new ActivePathResolver;
    $level = $r->level(fourLevelTree(), $r->resolve(fourLevelTree(), '/admin/sites/1/pages/9'));

    expect($level['parent']->getId())->toBe('pages')
        ->and($level['depth'])->toBe(3)
        ->and(array_map(fn ($n) => $n->getId(), $level['items']))->toBe(['page-9']);
});

it('falls back to the root level for unknown urls', function () {
    $r = new ActivePathResolver;
    $level = $r->level(fourLevelTree(), $r->resolve(fourLevelTree(), '/admin/unknown'));

    expect($level['parent'])->toBeNull()->and($level['items'])->toHaveCount(2);
});

it('does not treat /admin as a prefix match for unrelated roots', function () {
    $path = (new ActivePathResolver)->resolve(fourLevelTree(), '/admin/sites');

    expect($path[0]->getId())->toBe('sites');
});
