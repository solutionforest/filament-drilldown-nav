<?php

use Illuminate\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;
use SolutionForest\DrilldownNav\ChildDisplay;
use SolutionForest\DrilldownNav\RequestState;
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

/** Worker reuse (Octane): scoped state is dropped between requests, objects built once are reused. */
function nextRequest(): void
{
    Container::getInstance()->forgetScopedInstances();
}

it('re-evaluates children closures on the next request, even for items built once per worker', function () {
    $calls = 0;
    $item = NavItem::make('Sites')->url('/admin/sites')->children(function () use (&$calls) {
        $calls++;

        return [NavItem::make('Site '.$calls)->url('/admin/sites/'.$calls)];
    });

    expect($item->getChildren()[0]->getLabelText())->toBe('Site 1')
        ->and($item->getChildren()[0]->getLabelText())->toBe('Site 1'); // same request: memoized

    nextRequest();

    expect($item->getChildren()[0]->getLabelText())->toBe('Site 2')
        ->and($calls)->toBe(2);
});

it('looks a record up again on the next request', function () {
    $title = 'Alpha';
    $item = NavItem::make('Projects')->url('/admin/projects')
        ->resolveRecordUsing(fn (string $key) => $key)
        ->recordTitleUsing(function () use (&$title) {
            return $title;
        })
        ->record(fn () => []);

    expect($item->getRecordNode('/admin/projects/7')?->getLabelText())->toBe('Alpha');

    $title = 'Beta';
    expect($item->getRecordNode('/admin/projects/7')?->getLabelText())->toBe('Alpha'); // same request

    nextRequest();

    expect($item->getRecordNode('/admin/projects/7')?->getLabelText())->toBe('Beta');
});

it('keeps request state per item and frees it with the item', function () {
    $state = RequestState::current();
    $a = new stdClass;
    $b = new stdClass;

    expect($state->remember($a, 'k', fn () => 1))->toBe(1)
        ->and($state->remember($a, 'k', fn () => 2))->toBe(1)
        ->and($state->remember($b, 'k', fn () => 3))->toBe(3)
        ->and($state->remember($a, 'null', fn () => null))->toBeNull()
        ->and($state->remember($a, 'null', fn () => 'set'))->toBeNull(); // null results are remembered too
});

/** Phase B: modern navigation features. */
it('knows whether it expands in place, with the plugin default as fallback', function () {
    $plain = NavItem::make('A')->children([NavItem::make('B')]);

    expect($plain->isInline())->toBeFalse()
        ->and($plain->isInline(default: true))->toBeTrue()
        ->and($plain->inline()->isInline())->toBeTrue()
        ->and($plain->drilldown()->isInline(default: true))->toBeFalse(); // explicit beats the default
});

it('only opens its own level when it has children and is not inline', function () {
    $drill = NavItem::make('A')->children([NavItem::make('B')]);
    $inline = NavItem::make('A')->inline()->children([NavItem::make('B')]);
    $leaf = NavItem::make('Leaf');

    expect($drill->opensLevel())->toBeTrue()
        ->and($drill->opensLevel(inlineByDefault: true))->toBeFalse()
        ->and($inline->opensLevel())->toBeFalse()
        ->and($leaf->opensLevel())->toBeFalse();
});

it('does not drill into inline items: they stay in their parent level', function () {
    $tree = [
        NavItem::make('Team')->inline()->children([
            NavItem::make('Members')->url('/admin/team/members'),
        ]),
        NavItem::make('Other')->url('/admin/other'),
    ];

    $r = new ActivePathResolver;
    $level = $r->level($tree, $r->resolve($tree, '/admin/team/members', '/admin'), '/admin');

    expect($level['parent'])->toBeNull() // still the root list
        ->and(array_map(fn (NavItem $i) => $i->getLabelText(), $level['items']))->toBe(['Team', 'Other'])
        ->and($level['active']?->getLabelText())->toBe('Team');
});

it('drills past inline items to the nearest item that opens a level', function () {
    $tree = [
        NavItem::make('Docs')->url('/admin/docs')->children([
            NavItem::make('Guides')->url('/admin/docs/guides')->inline()->children([
                NavItem::make('Install')->url('/admin/docs/guides/install'),
            ]),
        ]),
    ];

    $r = new ActivePathResolver;
    $level = $r->level($tree, $r->resolve($tree, '/admin/docs/guides/install', '/admin'), '/admin');

    expect($level['title'])->toBe('Docs')
        ->and(array_map(fn (NavItem $i) => $i->getLabelText(), $level['items']))->toBe(['Guides'])
        ->and($level['back'])->toBe('/admin');
});

it('treats headings as non-clickable labels that never affect matching or links', function () {
    $heading = NavItem::heading('Manage')->divider();

    expect($heading->isHeading())->toBeTrue()
        ->and($heading->hasDivider())->toBeTrue()
        ->and($heading->getLinkUrl())->toBeNull()
        ->and(NavItem::make('x')->isHeading())->toBeFalse();

    $group = NavItem::make('G')->children([
        NavItem::heading('Section'),
        NavItem::make('First')->url('/first'),
    ]);

    expect($group->getLinkUrl())->toBe('/first'); // skips the heading

    $tree = [NavItem::heading('Top'), NavItem::make('Home')->url('/admin/home')];
    $r = new ActivePathResolver;
    $resolved = $r->resolve($tree, '/admin/home', '/admin');

    expect(array_map(fn (NavItem $i) => $i->getLabelText(), $resolved['path']))->toBe(['Home']);
});

it('supports expanded-by-default and an active icon', function () {
    $item = NavItem::make('Team')->inline()->defaultExpanded()->icon('heroicon-o-users')->activeIcon('heroicon-s-users');

    expect($item->isDefaultExpanded())->toBeTrue()
        ->and($item->getActiveIcon())->toBe('heroicon-s-users')
        ->and(NavItem::make('x')->isDefaultExpanded())->toBeFalse();
});

it('has a child display enum with drilldown and inline', function () {
    expect(ChildDisplay::Drilldown->value)->toBe('drilldown')
        ->and(ChildDisplay::Inline->value)->toBe('inline');
});
