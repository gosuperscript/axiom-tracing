<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

final class ResolutionTrace
{
    /** @var list<ResolutionTrace> */
    private array $children = [];

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(
        public readonly string $sourceType,
    ) {}

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * Human-readable label, set by the resolver via annotate('label', ...).
     */
    public function label(): ?string
    {
        $label = $this->metadata['label'] ?? null;

        return is_string($label) ? $label : null;
    }

    /** @return list<self> */
    public function children(): array
    {
        return $this->children;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function getMetadata(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Recursively collect all metadata entries with the given key
     * from this node and all descendants.
     *
     * @return list<mixed>
     */
    public function collect(string $key): array
    {
        $results = [];

        if (isset($this->metadata[$key])) {
            $results[] = $this->metadata[$key];
        }

        foreach ($this->children as $child) {
            $results = [...$results, ...$child->collect($key)];
        }

        return $results;
    }
}
