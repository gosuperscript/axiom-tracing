<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Tracing\ResolutionTrace;
use Superscript\Axiom\Tracing\TracedResult;

use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\Ok;

final class TracedResultTest extends TestCase
{
    public function test_exposes_result_and_trace(): void
    {
        $result = Ok(Some(42));
        $trace = new ResolutionTrace('TestSource');

        $traced = new TracedResult($result, $trace);

        $this->assertSame($result, $traced->result);
        $this->assertSame($trace, $traced->trace);
    }

    public function test_dump_returns_formatted_string(): void
    {
        $trace = new ResolutionTrace('App\\Sources\\StaticSource');
        $trace->addMetadata('label', 'static(int)');
        $trace->addMetadata('outcome', 'ok');
        $trace->addMetadata('value', 42);
        $trace->addMetadata('has_value', true);
        $trace->addMetadata('duration_ms', 0.01);

        $traced = new TracedResult(Ok(Some(42)), $trace);

        $dump = $traced->dump();

        $this->assertIsString($dump);
        $this->assertStringContainsString('StaticSource', $dump);
        $this->assertStringContainsString('[static(int)]', $dump);
    }
}
