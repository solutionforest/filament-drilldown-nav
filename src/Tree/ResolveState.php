<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

/**
 * Working state of one ActivePathResolver::resolve() run.
 *
 * @internal Not part of the public API: it may change in any release. See docs/api-stability.md.
 */
final class ResolveState
{
    /** @var array<int, NavItem> best match so far: the items from the root down to the match */
    public array $best = [];

    public int $bestLen = -1;

    public function __construct(
        public readonly string $path,
        public readonly ?string $rootPath,
    ) {}
}
