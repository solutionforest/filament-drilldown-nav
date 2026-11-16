<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

class ActivePathResolver
{
    /**
     * Returns the node path (root → deepest match) for the given URL path,
     * choosing the node whose URL is the longest prefix of the request path.
     *
     * @param  array<int, NavNode>  $tree
     * @return array<int, NavNode>
     */
    public function resolve(array $tree, string $path): array
    {
        $path = '/'.trim($path, '/');
        $best = [];
        $bestLen = -1;

        $walk = function (array $nodes, array $trail) use (&$walk, &$best, &$bestLen, $path): void {
            foreach ($nodes as $node) {
                if (! $node->isVisible()) {
                    continue;
                }
                $current = [...$trail, $node];
                $url = $node->getUrl();
                if ($url !== null) {
                    $u = '/'.trim($url, '/');
                    $matches = $path === $u || ($u !== '/' && str_starts_with($path, $u.'/'));
                    if ($matches && strlen($u) > $bestLen) {
                        $best = $current;
                        $bestLen = strlen($u);
                    }
                }
                $walk($node->getChildren(), $current);
            }
        };
        $walk($tree, []);

        return $best;
    }

    /**
     * The level to render: children of the deepest node on the path that has
     * children (or the root list if nothing matched). Includes the parent for "Back".
     *
     * @param  array<int, NavNode>  $tree
     * @param  array<int, NavNode>  $path
     * @return array{parent: ?NavNode, items: array<int, NavNode>, depth: int}
     */
    public function level(array $tree, array $path): array
    {
        for ($i = count($path) - 1; $i >= 0; $i--) {
            if ($path[$i]->hasChildren()) {
                return ['parent' => $path[$i], 'items' => $path[$i]->getChildren(), 'depth' => $i + 1];
            }
        }

        return ['parent' => null, 'items' => array_values(array_filter($tree, fn ($n) => $n->isVisible())), 'depth' => 0];
    }
}
