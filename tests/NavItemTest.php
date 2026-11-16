<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;
use SolutionForest\DrilldownNav\Tree\ActivePathResolver;
use SolutionForest\DrilldownNav\Tree\NavItem;

it('strips html from an Htmlable label for plain-text use', function () {
    expect(NavItem::make(new HtmlString('<b>Projects</b>'))->getLabelText())->toBe('Projects')
        ->and(NavItem::make('Plain')->getLabelText())->toBe('Plain');
});

it('evaluates badge and badge color lazily and hides empty badges', function () {
    $count = 0;
    $item = NavItem::make('Inbox')->badge(function () use (&$count) {
        $count++;

        return $count > 1 ? '5' : null;
    }, fn () => 'danger');

    expect($item->getBadge())->toBeNull()
        ->and($item->getBadge())->toBe('5')
        ->and($item->getBadgeColor())->toBe('danger')
        ->and(NavItem::make('x')->badge('')->getBadge())->toBeNull();
});

it('filters invisible children and evaluates visible() lazily', function () {
    $show = false;
    $parent = NavItem::make('P')->children([
        NavItem::make('A')->visible(fn () => $show),
        NavItem::make('B'),
    ]);

    // visibility is evaluated when children are first resolved, once per request
    expect(array_map(fn (NavItem $i) => $i->getLabelText(), $parent->getChildren()))->toBe(['B']);

    $show = true;
    expect($parent->getChildren())->toHaveCount(1); // memoized for the request
});

it('links a group to its first visible descendant with a url', function () {
    $group = NavItem::make('G')->children([
        NavItem::make('Hidden')->url('/h')->visible(false),
        NavItem::make('Sub')->children([NavItem::make('Deep')->url('/deep')]),
    ]);

    expect($group->getLinkUrl())->toBe('/deep')
        ->and(NavItem::make('Empty')->getLinkUrl())->toBeNull();
});

it('treats "create" and custom ignored keys as pages, not records', function () {
    $item = NavItem::make('Projects')->url('/admin/projects')
        ->ignoreRecordKeys(['create', 'import'])
        ->resolveRecordUsing(fn (string $key) => $key === '7' ? 'Bot' : null)
        ->record(fn () => []);

    expect($item->getRecordNode('/admin/projects/7/edit'))->not->toBeNull()
        ->and($item->getRecordNode('/admin/projects/create'))->toBeNull()
        ->and($item->getRecordNode('/admin/projects/import'))->toBeNull()
        ->and($item->getRecordNode('/admin/projects/99'))->toBeNull()
        ->and($item->getRecordNode('/admin/projects'))->toBeNull()
        ->and($item->getRecordNode('/admin/other/7'))->toBeNull();
});

it('treats a database error while resolving a record as "no such record"', function () {
    $item = NavItem::make('Projects')->url('/admin/projects')
        ->resolveRecordUsing(fn (string $key) => throw new QueryException('pgsql', 'select', [], new Exception('invalid input syntax')))
        ->record(fn () => []);

    expect($item->getRecordNode('/admin/projects/abc'))->toBeNull();
});

it('resolves each record only once per request', function () {
    $lookups = 0;
    $item = NavItem::make('Projects')->url('/admin/projects')
        ->resolveRecordUsing(function (string $key) use (&$lookups) {
            $lookups++;

            return 'Bot';
        })
        ->record(fn () => []);

    $item->getRecordNode('/admin/projects/7');
    $item->getRecordNode('/admin/projects/7/edit');

    expect($lookups)->toBe(1);
});

it('opens sub menus nested inside a record menu', function () {
    $tree = [
        NavItem::make('Projects')->url('/admin/projects')
            ->resolveRecordUsing(fn (string $key) => 'Bot')
            ->recordTitleUsing(fn () => 'Website redesign')
            ->record(fn () => [
                NavItem::make('Files')->children([
                    NavItem::make('Documents')->url('/admin/projects/7/documents'),
                    NavItem::make('Links')->url('/admin/projects/7/links'),
                ]),
            ]),
    ];

    $resolver = new ActivePathResolver;
    $level = $resolver->level($tree, $resolver->resolve($tree, '/admin/projects/7/documents', '/admin'), '/admin');

    expect($level['title'])->toBe('Files')
        ->and(array_map(fn (NavItem $i) => $i->getLabelText(), $level['items']))->toBe(['Documents', 'Links'])
        ->and((string) $level['active']?->getLabelText())->toBe('Documents');
});
