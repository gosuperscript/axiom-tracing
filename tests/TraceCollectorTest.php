<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Superscript\Axiom\Execution\Annotated;
use Superscript\Axiom\Execution\Entered;
use Superscript\Axiom\Execution\Event;
use Superscript\Axiom\Execution\Exited;
use Superscript\Axiom\Execution\Node;
use Superscript\Axiom\Execution\Threw;
use Superscript\Axiom\Tracing\TraceCollector;
use Superscript\Axiom\Types\NumberType;

use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\Ok;

final class TraceCollectorTest extends TestCase
{
    public function test_it_builds_a_timed_tree_from_ordered_events(): void
    {
        $times = [10.0, 10.1, 10.2, 10.3];
        $collector = new TraceCollector(static function () use (&$times): float {
            return (float) array_shift($times);
        });
        $root = new Node('Root', new NumberType);
        $child = new Node('Child', new NumberType);

        $collector->observe(new Entered($root));
        $collector->observe(new Annotated($root, 'label', 'root'));
        $collector->observe(new Entered($child));
        $collector->observe(new Annotated($child, 'label', 'child'));
        $collector->observe(new Exited($child, Ok(Some(2))));
        $collector->observe(new Exited($root, Ok(Some(2))));

        $trace = $collector->trace();
        $this->assertNotNull($trace);
        $this->assertSame('Root', $trace->node->sourceType);
        $this->assertSame('root', $trace->label());
        $this->assertSame(300.0, $trace->durationMs());
        $this->assertSame('Child', $trace->children()[0]->node->sourceType);
        $this->assertSame(100.0, $trace->children()[0]->durationMs());
    }

    public function test_it_records_a_throw_event(): void
    {
        $times = [1.0, 1.002];
        $collector = new TraceCollector(static function () use (&$times): float {
            return (float) array_shift($times);
        });
        $node = new Node('HostSource', new NumberType);

        $collector->observe(new Entered($node));
        $collector->observe(new Threw($node, new RuntimeException('boom')));

        $this->assertNotNull($collector->trace());
        $this->assertSame('threw', $collector->trace()->outcome());
        $this->assertSame('boom', $collector->trace()->error());
    }

    public function test_it_refuses_unbalanced_events(): void
    {
        $collector = new TraceCollector;

        $this->expectException(LogicException::class);
        $collector->observe(new Annotated(new Node('Orphan', new NumberType), 'key', 'value'));
    }

    public function test_it_refuses_a_second_root(): void
    {
        $collector = new TraceCollector;
        $first = new Node('First', new NumberType);
        $collector->observe(new Entered($first));
        $collector->observe(new Exited($first, Ok(Some(1))));

        $this->expectException(LogicException::class);
        $collector->observe(new Entered(new Node('Second', new NumberType)));
    }

    public function test_a_fresh_collector_has_no_trace(): void
    {
        $collector = new TraceCollector;
        $event = new class(new Node('FutureSource', new NumberType)) implements Event
        {
            public function __construct(public Node $node) {}
        };

        $collector->observe($event);

        $this->assertNull($collector->trace());
    }
}
