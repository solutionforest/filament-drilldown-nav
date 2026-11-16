<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

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
     * one of its ancestors (e.g. a record's "Overview"): those match exactly only.
     *
     * @param  array<int, NavItem>  $tree
     * @param  string|null  $rootPath  panel root (e.g. "/admin")
     * @return array{path: array<int, NavItem>}
     */
    public function resolve(array $tree, string $path, ?string $rootPath = null): array
    {
        $path = self::normalise($path);
        $rootPath = $rootPath !== null ? self::normalise($rootPath) : null;
        $best = [];
        $bestLen = -1;

        $walk = function (array $items, array $trail, array $ancestorUrls) use (&$walk, &$best, &$bestLen, $path, $rootPath): void {
            foreach ($items as $item) {
                if (! $item->isVisible()) {
                    continue;
                }

                $current = [...$trail, $item];
                $url = $item->getUrl();

                if ($url === null) {
                    // A URL-less group may contain the match.
                    $walk($item->getChildren(), $current, $ancestorUrls);

                    continue;
                }

                $u = self::normalise($url);
                $exactOnly = $u === '/' || $u === $rootPath || in_array($u, $ancestorUrls, true);
                $exact = $path === $u;

                if (! $exact && ($exactOnly || ! str_starts_with($path, $u.'/'))) {
                    continue;
                }

                if (strlen($u) >= $bestLen) {
                    $best = $current;
                    $bestLen = strlen($u);
                }

                $urls = [...$ancestorUrls, $u];

                if (($record = $item->getRecordNode($path)) !== null) {
                    $walk([$record], $current, $urls);
                }

                $walk($item->getChildren(), $current, $urls);
            }
        };
        $walk($tree, [], []);

        return ['path' => $best];
    }

    /**
     * The level to render: the children of the deepest item on the path that has children.
     *
     * @param  array<int, NavItem>  $tree
     * @param  array{path: array<int, NavItem>}  $resolved
     * @return array{parent: ?NavItem, title: ?string, items: array<int, NavItem>, depth: int, back: ?string, active: ?NavItem}
     */
    public function level(array $tree, array $resolved, ?string $homeUrl = null): array
    {
        $path = $resolved['path'];

        for ($i = count($path) - 1; $i >= 0; $i--) {
            if ($path[$i]->hasChildren()) {
                $grand = $i > 0 ? $path[$i - 1] : null;

                return [
                    'parent' => $path[$i],
                    'title' => (string) $path[$i]->getLabel(),
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
