<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Axiom\ResolutionInspector;

final class TracingResolutionInspector implements ResolutionInspector
{
    private ?ResolutionTrace $current = null;

    public function setCurrent(?ResolutionTrace $node): void
    {
        $this->current = $node;
    }

    public function current(): ?ResolutionTrace
    {
        return $this->current;
    }

    /**
     * Annotations from resolvers land on whatever tree node is current.
     */
    public function annotate(string $key, mixed $value): void
    {
        $this->current?->addMetadata($key, $value);
    }
}
