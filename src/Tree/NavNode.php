<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

use Closure;

class NavNode
{
    /** @var array<int, NavNode>|Closure|null */
    protected array|Closure|null $children = null;

    protected ?string $icon = null;

    protected ?string $url = null;

    protected bool|Closure $visible = true;

    final public function __construct(protected string $id, protected string $label) {}

    public static function make(string $id, ?string $label = null): static
    {
        return new static($id, $label ?? $id);
    }

    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /** @param array<int, NavNode>|Closure $children */
    public function children(array|Closure $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function isVisible(): bool
    {
        return (bool) ($this->visible instanceof Closure ? ($this->visible)() : $this->visible);
    }

    /** @return array<int, NavNode> visible children only */
    public function getChildren(): array
    {
        $children = $this->children instanceof Closure ? ($this->children)() : ($this->children ?? []);

        return array_values(array_filter($children, fn (NavNode $n) => $n->isVisible()));
    }

    public function hasChildren(): bool
    {
        return $this->getChildren() !== [];
    }
}
