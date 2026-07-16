<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Monads\Option\Option;
use Superscript\Monads\Result\Result;
use Throwable;

final readonly class TracedResult
{
    /** @param Result<Option<mixed>, Throwable> $result */
    public function __construct(
        public Result $result,
        public ExecutionTrace $trace,
    ) {}

    public function dump(): string
    {
        return (new TraceFormatter)->format($this->trace);
    }
}
