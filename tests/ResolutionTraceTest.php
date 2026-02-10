<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Tracing\ResolutionTrace;

final class ResolutionTraceTest extends TestCase
{
    public function test_stores_source_type(): void
    {
        $trace = new ResolutionTrace('App\\Source\\MySource');

        $this->assertSame('App\\Source\\MySource', $trace->sourceType);
    }

    public function test_add_and_retrieve_children(): void
    {
        $parent = new ResolutionTrace('Parent');
        $child1 = new ResolutionTrace('Child1');
        $child2 = new ResolutionTrace('Child2');

        $parent->addChild($child1);
        $parent->addChild($child2);

        $this->assertCount(2, $parent->children());
        $this->assertSame($child1, $parent->children()[0]);
        $this->assertSame($child2, $parent->children()[1]);
    }

    public function test_add_and_retrieve_metadata(): void
    {
        $trace = new ResolutionTrace('Source');

        $trace->addMetadata('key', 'value');
        $trace->addMetadata('count', 42);

        $this->assertSame('value', $trace->getMetadata('key'));
        $this->assertSame(42, $trace->getMetadata('count'));
        $this->assertSame(['key' => 'value', 'count' => 42], $trace->metadata());
    }

    public function test_get_metadata_returns_null_for_missing_key(): void
    {
        $trace = new ResolutionTrace('Source');

        $this->assertNull($trace->getMetadata('nonexistent'));
    }

    public function test_label_returns_string_metadata(): void
    {
        $trace = new ResolutionTrace('Source');
        $trace->addMetadata('label', 'MyLabel');

        $this->assertSame('MyLabel', $trace->label());
    }

    public function test_label_returns_null_when_not_set(): void
    {
        $trace = new ResolutionTrace('Source');

        $this->assertNull($trace->label());
    }

    public function test_label_returns_null_for_non_string_value(): void
    {
        $trace = new ResolutionTrace('Source');
        $trace->addMetadata('label', 42);

        $this->assertNull($trace->label());
    }

    public function test_collect_gathers_metadata_from_tree(): void
    {
        $root = new ResolutionTrace('Root');
        $root->addMetadata('tag', 'root');

        $child1 = new ResolutionTrace('Child1');
        $child1->addMetadata('tag', 'child1');

        $child2 = new ResolutionTrace('Child2');
        // No 'tag' on child2

        $grandchild = new ResolutionTrace('Grandchild');
        $grandchild->addMetadata('tag', 'grandchild');

        $root->addChild($child1);
        $root->addChild($child2);
        $child2->addChild($grandchild);

        $collected = $root->collect('tag');

        $this->assertSame(['root', 'child1', 'grandchild'], $collected);
    }

    public function test_collect_returns_empty_when_key_not_found(): void
    {
        $root = new ResolutionTrace('Root');
        $child = new ResolutionTrace('Child');
        $root->addChild($child);

        $this->assertSame([], $root->collect('nonexistent'));
    }

    public function test_children_starts_empty(): void
    {
        $trace = new ResolutionTrace('Source');

        $this->assertSame([], $trace->children());
    }

    public function test_metadata_starts_empty(): void
    {
        $trace = new ResolutionTrace('Source');

        $this->assertSame([], $trace->metadata());
    }

    public function test_metadata_overwrites_same_key(): void
    {
        $trace = new ResolutionTrace('Source');
        $trace->addMetadata('key', 'first');
        $trace->addMetadata('key', 'second');

        $this->assertSame('second', $trace->getMetadata('key'));
    }
}
