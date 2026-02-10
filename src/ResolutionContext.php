<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Axiom\ResolutionInspector;

final class ResolutionContext implements ResolutionInspector
{
    /** @var array<string, list<mixed>> */
    private array $annotations = [];

    public function annotate(string $key, mixed $value): void
    {
        $this->annotations[$key][] = $value;
    }

    /**
     * Get the last annotation for a key, or null if none.
     */
    public function get(string $key): mixed
    {
        $values = $this->annotations[$key] ?? [];

        return $values !== [] ? end($values) : null;
    }

    /**
     * Get all annotations for a key.
     *
     * @return list<mixed>
     */
    public function all(string $key): array
    {
        return $this->annotations[$key] ?? [];
    }

    /**
     * Get all annotations and clear the context.
     *
     * @return array<string, list<mixed>>
     */
    public function flush(): array
    {
        $annotations = $this->annotations;
        $this->annotations = [];

        return $annotations;
    }

    /**
     * Clear all annotations.
     */
    public function reset(): void
    {
        $this->annotations = [];
    }
}
