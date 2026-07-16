<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Axiom\Execution\Node;
use Superscript\Axiom\Program;

final class Tracing
{
    /** @param array<string, mixed> $bindings */
    public static function run(Program $program, array $bindings = []): TracedResult
    {
        $startedAt = microtime(true);
        $collector = new TraceCollector;
        $result = $program->call($bindings, $collector);
        $trace = $collector->trace();

        if ($trace === null) {
            // Boundary admission can refuse before compiled evaluation starts.
            $trace = new ExecutionTrace(new Node(Program::class, $program->returns), $startedAt);
            $trace->complete($result, microtime(true));
        }

        return new TracedResult($result, $trace);
    }
}
