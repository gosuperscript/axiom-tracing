<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use Superscript\Axiom\ResolutionInspector;
use Superscript\Axiom\Tracing\ResolutionContext;

final class ResolutionContextTest extends TestCase
{
    public function test_implements_resolution_inspector(): void
    {
        $context = new ResolutionContext();

        $this->assertInstanceOf(ResolutionInspector::class, $context);
    }

    public function test_annotate_stores_values_and_get_returns_last(): void
    {
        $context = new ResolutionContext();

        $context->annotate('key', 'first');
        $context->annotate('key', 'second');

        $this->assertSame('second', $context->get('key'));
    }

    public function test_all_returns_all_values_in_order(): void
    {
        $context = new ResolutionContext();

        $context->annotate('key', 'a');
        $context->annotate('key', 'b');
        $context->annotate('key', 'c');

        $this->assertSame(['a', 'b', 'c'], $context->all('key'));
    }

    public function test_get_returns_null_for_unknown_keys(): void
    {
        $context = new ResolutionContext();

        $this->assertNull($context->get('nonexistent'));
    }

    public function test_all_returns_empty_array_for_unknown_keys(): void
    {
        $context = new ResolutionContext();

        $this->assertSame([], $context->all('nonexistent'));
    }

    public function test_flush_returns_all_annotations_and_clears_state(): void
    {
        $context = new ResolutionContext();

        $context->annotate('a', 1);
        $context->annotate('b', 2);
        $context->annotate('a', 3);

        $flushed = $context->flush();

        $this->assertSame([1, 3], $flushed['a']);
        $this->assertSame([2], $flushed['b']);

        // State should be cleared
        $this->assertNull($context->get('a'));
        $this->assertNull($context->get('b'));
        $this->assertSame([], $context->all('a'));
    }

    public function test_reset_clears_state_without_returning(): void
    {
        $context = new ResolutionContext();

        $context->annotate('key', 'value');
        $context->reset();

        $this->assertNull($context->get('key'));
        $this->assertSame([], $context->all('key'));
    }

    public function test_multiple_keys_are_independent(): void
    {
        $context = new ResolutionContext();

        $context->annotate('foo', 'bar');
        $context->annotate('baz', 'qux');

        $this->assertSame('bar', $context->get('foo'));
        $this->assertSame('qux', $context->get('baz'));
    }

    public function test_stores_mixed_value_types(): void
    {
        $context = new ResolutionContext();

        $context->annotate('int', 42);
        $context->annotate('float', 3.14);
        $context->annotate('bool', true);
        $context->annotate('array', ['a', 'b']);
        $context->annotate('null', null);

        $this->assertSame(42, $context->get('int'));
        $this->assertSame(3.14, $context->get('float'));
        $this->assertTrue($context->get('bool'));
        $this->assertSame(['a', 'b'], $context->get('array'));
        $this->assertNull($context->get('null'));

        // But all() should still have the null entry
        $this->assertSame([null], $context->all('null'));
    }
}
