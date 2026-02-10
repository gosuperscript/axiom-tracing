<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use Superscript\Axiom\ResolutionInspector;
use Superscript\Axiom\Tracing\ResolutionTrace;
use Superscript\Axiom\Tracing\TracingResolutionInspector;

final class TracingResolutionInspectorTest extends TestCase
{
    public function test_implements_resolution_inspector(): void
    {
        $inspector = new TracingResolutionInspector();

        $this->assertInstanceOf(ResolutionInspector::class, $inspector);
    }

    public function test_current_starts_as_null(): void
    {
        $inspector = new TracingResolutionInspector();

        $this->assertNull($inspector->current());
    }

    public function test_set_and_get_current(): void
    {
        $inspector = new TracingResolutionInspector();
        $node = new ResolutionTrace('Source');

        $inspector->setCurrent($node);

        $this->assertSame($node, $inspector->current());
    }

    public function test_set_current_to_null(): void
    {
        $inspector = new TracingResolutionInspector();
        $node = new ResolutionTrace('Source');

        $inspector->setCurrent($node);
        $inspector->setCurrent(null);

        $this->assertNull($inspector->current());
    }

    public function test_annotate_adds_metadata_to_current_node(): void
    {
        $inspector = new TracingResolutionInspector();
        $node = new ResolutionTrace('Source');

        $inspector->setCurrent($node);
        $inspector->annotate('label', 'test');
        $inspector->annotate('custom', 42);

        $this->assertSame('test', $node->getMetadata('label'));
        $this->assertSame(42, $node->getMetadata('custom'));
    }

    public function test_annotate_is_noop_when_no_current_node(): void
    {
        $inspector = new TracingResolutionInspector();

        // Should not throw
        $inspector->annotate('key', 'value');

        $this->assertNull($inspector->current());
    }

    public function test_annotations_land_on_correct_node_after_switching(): void
    {
        $inspector = new TracingResolutionInspector();
        $node1 = new ResolutionTrace('Source1');
        $node2 = new ResolutionTrace('Source2');

        $inspector->setCurrent($node1);
        $inspector->annotate('key', 'on_node1');

        $inspector->setCurrent($node2);
        $inspector->annotate('key', 'on_node2');

        $this->assertSame('on_node1', $node1->getMetadata('key'));
        $this->assertSame('on_node2', $node2->getMetadata('key'));
    }
}
