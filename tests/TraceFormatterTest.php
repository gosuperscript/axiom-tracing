<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Superscript\Axiom\Execution\Node;
use Superscript\Axiom\Tracing\ExecutionTrace;
use Superscript\Axiom\Tracing\TracedResult;
use Superscript\Axiom\Tracing\TraceFormatter;
use Superscript\Axiom\Types\NumberType;

use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

final class TraceFormatterTest extends TestCase
{
    public function test_it_formats_a_tree_with_ordered_annotations(): void
    {
        $root = new ExecutionTrace(new Node('App\\InfixExpression', new NumberType), 1.0);
        $root->annotate('label', '+');
        $root->annotate('left', 10);
        $root->annotate('active', true);
        $root->complete(Ok(Some(30)), 1.010);

        $child = new ExecutionTrace(new Node('App\\StaticSource', new NumberType), 1.001);
        $child->annotate('label', 'static(int)');
        $child->complete(Ok(Some(10)), 1.0011);
        $root->addChild($child);

        $second = new ExecutionTrace(new Node('App\\StaticSource', new NumberType), 1.002);
        $second->annotate('label', 'static(int)');
        $second->complete(Ok(Some(20)), 1.0022);
        $root->addChild($second);

        $this->assertSame(implode("\n", [
            'InfixExpression [+] — ok, value: 30, 10ms',
            '    left: 10',
            '    active: true',
            '    ├── StaticSource [static(int)] — ok, value: 10, 0.1ms',
            '    └── StaticSource [static(int)] — ok, value: 20, 0.2ms',
        ]), (new TraceFormatter)->format($root));
    }

    public function test_it_formats_errors_json_and_a_traced_result(): void
    {
        $trace = new ExecutionTrace(new Node('App\\LookupSource', new NumberType), 1.0);
        $trace->annotate('query', ['id' => 42]);
        $result = Err(new RuntimeException('not found'));
        $trace->complete($result, 1.001);

        $dump = (new TracedResult($result, $trace))->dump();

        $this->assertStringContainsString('LookupSource — err, error: not found, 1ms', $dump);
        $this->assertStringContainsString('query: {"id":42}', $dump);
    }

    public function test_unencodable_annotations_do_not_break_the_dump(): void
    {
        $stream = fopen('php://memory', 'r');
        $this->assertIsResource($stream);
        $trace = new ExecutionTrace(new Node('App\\StreamSource', new NumberType), 1.0);
        $trace->annotate('stream', $stream);

        $dump = (new TraceFormatter)->format($trace);
        fclose($stream);

        $this->assertStringContainsString('stream: resource (stream)', $dump);
    }
}
