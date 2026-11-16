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
     * is the longest prefix wins. A record menu counts as one extra level below its item.
     *
     * @param  array<int, NavItem>  $tree
     * @param  string|null  $rootPath  panel root (e.g. "/admin"); matches exactly only, never as a prefix
     * @return array{path: array<int, NavItem>, record: ?array{item: NavItem, title: string, menu: array<int, NavItem>}}
     */
    public function resolve(array $tree, string $path, ?string $rootPath = null): array
    {
        $path = self::normalise($path);
        $rootPath = $rootPath !== null ? self::normalise($rootPath) : null;
        $best = ['path' => [], 'record' => null];
        $bestLen = -1;

        $walk = function (array $items, array $trail) use (&$walk, &$best, &$bestLen, $path, $rootPath): void {
            foreach ($items as $item) {
                if (! $item->isVisible()) {
                    continue;
                }

                $current = [...$trail, $item];
                $url = $item->getUrl();
                $onPath = $url === null; // a URL-less group may contain the match

                if ($url !== null) {
                    $u = self::normalise($url);
                    $exact = $path === $u;
                    $prefix = $u !== '/' && $u !== $rootPath && str_starts_with($path, $u.'/');
                    $onPath = $exact || $prefix;

                    if ($onPath && strlen($u) > $bestLen) {
                        $best = ['path' => $current, 'record' => null];
                        $bestLen = strlen($u);

                        if (($ctx = $item->getRecordContext($path)) !== null) {
                            $best['record'] = ['item' => $item, ...$ctx];
                        }
                    }
                }

                if ($onPath) {
                    $walk($item->getChildren(), $current);
                }
            }
        };
        $walk($tree, []);

        // Inside a record: refine the match among the record menu (longest prefix).
        if ($best['record'] !== null) {
            $recordBest = null;
            $len = -1;
            foreach ($best['record']['menu'] as $child) {
                $u = $child->getUrl() !== null ? self::normalise($child->getUrl()) : null;
                if ($u !== null && ($path === $u || str_starts_with($path, $u.'/')) && strlen($u) > $len) {
                    $recordBest = $child;
                    $len = strlen($u);
                }
            }
            $best['recordActive'] = $recordBest;
        }

        return $best;
    }

    /**
     * The level to render, as: parent (null at root), items, depth, title, backUrl.
     *
     * @param  array<int, NavItem>  $tree
     * @param  array{path: array<int, NavItem>, record: ?array<string, mixed>}  $resolved
     * @return array{parent: ?NavItem, title: ?string, items: array<int, NavItem>, depth: int, back: ?string, active: ?NavItem}
     */
    public function level(array $tree, array $resolved, ?string $homeUrl = null): array
    {
        $path = $resolved['path'];

        if ($resolved['record'] !== null) {
            $item = $resolved['record']['item'];

            return [
                'parent' => $item,
                'title' => $resolved['record']['title'],
                'items' => $resolved['record']['menu'],
                'depth' => count($path) + 1,
                'back' => $item->getUrl(),
                'active' => $resolved['recordActive'] ?? null,
            ];
        }

        for ($i = count($path) - 1; $i >= 0; $i--) {
            if ($path[$i]->hasChildren()) {
                $grand = $i > 0 ? $path[$i - 1] : null;

                return [
                    'parent' => $path[$i],
                    'title' => null,
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
