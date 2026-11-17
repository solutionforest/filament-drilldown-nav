<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Closure;
use Filament\Navigation\NavigationItem;
use Illuminate\Container\Container;
use SolutionForest\DrilldownNav\Tree\NavItem;
use WeakMap;

/**
 * Everything the plugin remembers while serving ONE request.
 *
 * Long-lived workers (Octane, queue workers) keep panels, plugins and any NavItem you built
 * outside a closure alive between requests, so nothing request-specific may be stored on
 * them. This object is bound as a *scoped* singleton: the container discards it after each
 * request, and memoized values go with it.
 *
 * @internal Not part of the public API: it may change in any release. See docs/api-stability.md.
 */
final class RequestState
{
    /** @var array<string, array<int, NavItem>> evaluated item trees, per panel id */
    public array $trees = [];

    public bool $capturingNativeNavigation = false;

    public bool $capturedNativeNavigation = false;

    /** @var array<int, NavigationItem> */
    public array $nativeItems = [];

    /** @var WeakMap<object, array<string, mixed>> */
    private WeakMap $memo;

    public function __construct()
    {
        /** @var WeakMap<object, array<string, mixed>> $memo */
        $memo = new WeakMap;
        $this->memo = $memo;
    }

    /** The state of the current request (also works outside a booted application, e.g. in unit tests). */
    public static function current(): self
    {
        $container = Container::getInstance();

        if (! $container->bound(self::class)) {
            $container->scoped(self::class);
        }

        return $container->make(self::class);
    }

    /**
     * Compute $callback once per request for ($owner, $key). Null results are remembered too.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(object $owner, string $key, Closure $callback): mixed
    {
        $values = $this->memo[$owner] ?? [];

        if (! array_key_exists($key, $values)) {
            $values[$key] = $callback();
            $this->memo[$owner] = $values;
        }

        /** @var T */
        return $values[$key];
    }
}
