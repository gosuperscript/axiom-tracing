<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Monads\Result\Result;

/**
 * Pairs a resolution Result with its trace tree.
 */
final readonly class TracedResult
{
    public function __construct(
        public Result $result,
        public ResolutionTrace $trace,
    ) {}

    /**
     * Render the trace as a readable string.
     */
    public function dump(): string
    {
        return (new TraceFormatter())->format($this->trace);
    }
}
