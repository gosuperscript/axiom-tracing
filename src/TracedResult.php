<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Monads\Result\Result;

/**
 * Pairs a compiled {@see \Superscript\Axiom\Program}'s invocation Result with
 * the flat annotation log its evaluation emitted (a {@see ResolutionContext}
 * snapshot, `array<string, list<mixed>>`).
 */
final readonly class TracedResult
{
    /**
     * @param Result<\Superscript\Monads\Option\Option<mixed>, \Throwable> $result
     * @param array<string, list<mixed>> $log
     */
    public function __construct(
        public Result $result,
        public array $log,
    ) {}

    /**
     * Render the annotation log as a readable, order-preserving string.
     */
    public function dump(): string
    {
        return (new TraceFormatter())->format($this->log);
    }
}
