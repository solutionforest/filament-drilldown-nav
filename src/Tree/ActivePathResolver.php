<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

/**
 * @internal Not part of the public API: it may change in any release. See docs/api-stability.md.
 */
class ActivePathResolver
{
    /** Path part of a URL (absolute or relative), with a single leading slash and no trailing slash. */
    public static function normalise(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return '/'.trim($path, '/');
    }

    /**
     * Returns the item path (root → deepest match) for the request path: the item whose URL
     * is the longest prefix wins; on a tie the deeper item wins.
     *
     * A record menu is one more level of the tree: inside a record, the item gets a
     * record node (its URL = list URL + record key) whose children are the record menu,
     * and matching continues into it exactly like anywhere else.
     *
     * An item matches by prefix, except when its URL is the panel root or the same URL as
     * one of its ancestors (e.g. a record's "Overview"): those match exactly only. Extra URLs
     * from NavItem::activeOn() match the same way, as if they were the item's own;
     * NavItem::activeWhen() counts as an exact match.
     *
     * @param  array<int, NavItem>  $tree
     * @param  string|null  $rootPath  panel root (e.g. "/admin")
     * @return array{path: array<int, NavItem>}
     */
    public function resolve(array $tree, string $path, ?string $rootPath = null): array
    {
        $state = new ResolveState(
            path: self::normalise($path),
            rootPath: $rootPath !== null ? self::normalise($rootPath) : null,
        );

        $this->walk($tree, [], [], false, $state);

        return ['path' => $state->best];
    }

    /**
     * @param  array<int, NavItem>  $items
     * @param  array<int, NavItem>  $trail  items from the root down to (not including) $items
     * @param  array<int, string>  $ancestorUrls
     * @param  bool  $conditionOnly  below an item that does not match the URL, only activeWhen() can match
     *                               (static children only, so lazy children closures still run for the
     *                               current path alone)
     */
    private function walk(array $items, array $trail, array $ancestorUrls, bool $conditionOnly, ResolveState $state): void
    {
        foreach ($items as $item) {
            if (! $item->isVisible()) {
                continue;
            }

            $current = [...$trail, $item];
            $url = $item->getUrl();

            if ($url === null) {
                // A URL-less group may contain the match.
                $this->walk($item->getChildren(), $current, $ancestorUrls, $conditionOnly, $state);

                continue;
            }

            $u = self::normalise($url);
            $matchLen = -1;

            // Its own URL first, then any extra URLs from activeOn(); the longest match counts.
            $candidates = $conditionOnly ? [] : [$u, ...array_map(self::normalise(...), $item->getActiveUrls())];

            foreach ($candidates as $candidate) {
                $exactOnly = $candidate === '/' || $candidate === $state->rootPath || in_array($candidate, $ancestorUrls, true);

                if ($state->path === $candidate || (! $exactOnly && str_starts_with($state->path, $candidate.'/'))) {
                    $matchLen = max($matchLen, strlen($candidate));
                }
            }

            // activeWhen(): counts as an exact match of the request path.
            if ($item->isActiveByCondition()) {
                $matchLen = strlen($state->path);
            }

            if ($matchLen < 0) {
                if ($item->hasStaticChildren()) {
                    $this->walk($item->getChildren(), $current, [...$ancestorUrls, $u], true, $state);
                }

                continue;
            }

            if ($matchLen >= $state->bestLen) {
                $state->best = $current;
                $state->bestLen = $matchLen;
            }

            $urls = [...$ancestorUrls, $u];
            $record = $item->getRecordNode($state->path);

            if ($record !== null) {
                $this->walk([$record], $current, $urls, false, $state);
            }

            $this->walk($item->getChildren(), $current, $urls, false, $state);
        }
    }

    /**
     * The level to render: the children of the deepest item on the path that has children.
     *
     * @param  array<int, NavItem>  $tree
     * @param  array{path: array<int, NavItem>}  $resolved
     * @return array{parent: ?NavItem, title: ?string, items: array<int, NavItem>, depth: int, back: ?string, active: ?NavItem}
     */
    public function level(array $tree, array $resolved, ?string $homeUrl = null, bool $inlineByDefault = false): array
    {
        $path = $resolved['path'];

        for ($i = count($path) - 1; $i >= 0; $i--) {
            // Items that expand in place do not open a level of their own.
            if ($path[$i]->opensLevel($inlineByDefault)) {
                $grand = $i > 0 ? $path[$i - 1] : null;

                return [
                    'parent' => $path[$i],
                    'title' => $path[$i]->getLabelText(),
                    'items' => $path[$i]->getChildren(),
                    'depth' => $i + 1,
                    'back' => $grand?->getLinkUrl() ?? $homeUrl,
                    'active' => $path[$i + 1] ?? null,
                ];
            }
        }

        return [
            'parent' => null,
            'title' => null,
            'items' => array_values(array_filter($tree, fn ($n) => $n->isVisible())),
            'depth' => 0,
            'back' => null,
            'active' => $path[0] ?? null,
        ];
    }
}
