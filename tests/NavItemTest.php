<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;
use SolutionForest\DrilldownNav\Tree\ActivePathResolver;
use SolutionForest\DrilldownNav\Tree\NavItem;

it('strips html from an Htmlable label for plain-text use', function () {
    expect(NavItem::make(new HtmlString('<b>Agents</b>'))->getLabelText())->toBe('Agents')
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
    $item = NavItem::make('Agents')->url('/admin/agents')
        ->ignoreRecordKeys(['create', 'import'])
        ->resolveRecordUsing(fn (string $key) => $key === '7' ? 'Bot' : null)
        ->record(fn () => []);

    expect($item->getRecordNode('/admin/agents/7/edit'))->not->toBeNull()
        ->and($item->getRecordNode('/admin/agents/create'))->toBeNull()
        ->and($item->getRecordNode('/admin/agents/import'))->toBeNull()
        ->and($item->getRecordNode('/admin/agents/99'))->toBeNull()
        ->and($item->getRecordNode('/admin/agents'))->toBeNull()
        ->and($item->getRecordNode('/admin/other/7'))->toBeNull();
});

it('treats a database error while resolving a record as "no such record"', function () {
    $item = NavItem::make('Agents')->url('/admin/agents')
        ->resolveRecordUsing(fn (string $key) => throw new QueryException('pgsql', 'select', [], new Exception('invalid input syntax')))
        ->record(fn () => []);

    expect($item->getRecordNode('/admin/agents/abc'))->toBeNull();
});

it('resolves each record only once per request', function () {
    $lookups = 0;
    $item = NavItem::make('Agents')->url('/admin/agents')
        ->resolveRecordUsing(function (string $key) use (&$lookups) {
            $lookups++;

            return 'Bot';
        })
        ->record(fn () => []);

    $item->getRecordNode('/admin/agents/7');
    $item->getRecordNode('/admin/agents/7/edit');

    expect($lookups)->toBe(1);
});

it('opens sub menus nested inside a record menu', function () {
    $tree = [
        NavItem::make('Agents')->url('/admin/agents')
            ->resolveRecordUsing(fn (string $key) => 'Bot')
            ->recordTitleUsing(fn () => 'Support bot')
            ->record(fn () => [
                NavItem::make('Knowledge')->children([
                    NavItem::make('Documents')->url('/admin/agents/7/documents'),
                    NavItem::make('Websites')->url('/admin/agents/7/websites'),
                ]),
            ]),
    ];

    $resolver = new ActivePathResolver;
    $level = $resolver->level($tree, $resolver->resolve($tree, '/admin/agents/7/documents', '/admin'), '/admin');

    expect($level['title'])->toBe('Knowledge')
        ->and(array_map(fn (NavItem $i) => $i->getLabelText(), $level['items']))->toBe(['Documents', 'Websites'])
        ->and((string) $level['active']?->getLabelText())->toBe('Documents');
});
