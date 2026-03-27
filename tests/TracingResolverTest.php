<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Superscript\Axiom\Operators\DefaultOverloader;
use Superscript\Axiom\Operators\OperatorOverloader;
use Superscript\Axiom\Resolvers\DelegatingResolver;
use Superscript\Axiom\Resolvers\InfixResolver;
use Superscript\Axiom\Resolvers\Resolver;
use Superscript\Axiom\Resolvers\StaticResolver;
use Superscript\Axiom\Resolvers\SymbolResolver;
use Superscript\Axiom\Resolvers\ValueResolver;
use Superscript\Axiom\Source;
use Superscript\Axiom\Sources\InfixExpression;
use Superscript\Axiom\Sources\StaticSource;
use Superscript\Axiom\Sources\SymbolSource;
use Superscript\Axiom\Sources\TypeDefinition;
use Superscript\Axiom\SymbolRegistry;
use Superscript\Axiom\Tracing\TracedResult;
use Superscript\Axiom\Tracing\TracingResolver;
use Superscript\Axiom\Tracing\ResolutionTrace;
use Superscript\Axiom\Types\NumberType;
use Superscript\Axiom\Types\StringType;

final class TracingResolverTest extends TestCase
{
    private function createResolver(array $symbols = []): TracingResolver
    {
        $delegating = new DelegatingResolver([
            StaticSource::class => StaticResolver::class,
            InfixExpression::class => InfixResolver::class,
            SymbolSource::class => SymbolResolver::class,
            TypeDefinition::class => ValueResolver::class,
        ]);

        $delegating->instance(OperatorOverloader::class, new DefaultOverloader());

        if ($symbols !== []) {
            $delegating->instance(SymbolRegistry::class, new SymbolRegistry($symbols));
        }

        return new TracingResolver($delegating);
    }

    // ---------------------------------------------------------------
    // Tree structure tests
    // ---------------------------------------------------------------

    public function test_static_source_produces_single_root_node(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(new StaticSource(42));

        $this->assertSame(StaticSource::class, $traced->trace->sourceType);
        $this->assertSame([], $traced->trace->children());
    }

    public function test_type_definition_wrapping_static_produces_root_with_one_child(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new TypeDefinition(new NumberType(), new StaticSource(42))
        );

        $this->assertSame(TypeDefinition::class, $traced->trace->sourceType);
        $this->assertCount(1, $traced->trace->children());
        $this->assertSame(StaticSource::class, $traced->trace->children()[0]->sourceType);
    }

    public function test_infix_expression_produces_branching_children(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new InfixExpression(
                new StaticSource(10),
                '+',
                new StaticSource(20),
            )
        );

        $this->assertSame(InfixExpression::class, $traced->trace->sourceType);
        $this->assertCount(2, $traced->trace->children());
        $this->assertSame(StaticSource::class, $traced->trace->children()[0]->sourceType);
        $this->assertSame(StaticSource::class, $traced->trace->children()[1]->sourceType);
    }

    public function test_mixed_nesting_produces_correct_structure(): void
    {
        $tracer = $this->createResolver();

        // TypeDefinition -> InfixExpression -> (StaticSource, StaticSource)
        $traced = $tracer->traced(
            new TypeDefinition(
                new NumberType(),
                new InfixExpression(
                    new StaticSource(5),
                    '*',
                    new StaticSource(3),
                ),
            )
        );

        $root = $traced->trace;
        $this->assertSame(TypeDefinition::class, $root->sourceType);
        $this->assertCount(1, $root->children());

        $infix = $root->children()[0];
        $this->assertSame(InfixExpression::class, $infix->sourceType);
        $this->assertCount(2, $infix->children());

        $this->assertSame(StaticSource::class, $infix->children()[0]->sourceType);
        $this->assertSame(StaticSource::class, $infix->children()[1]->sourceType);
    }

    public function test_symbol_resolution_produces_correct_tree(): void
    {
        $tracer = $this->createResolver([
            'rate' => new StaticSource(100),
        ]);

        $traced = $tracer->traced(new SymbolSource('rate'));

        $this->assertSame(SymbolSource::class, $traced->trace->sourceType);
        $this->assertCount(1, $traced->trace->children());
        $this->assertSame(StaticSource::class, $traced->trace->children()[0]->sourceType);
    }

    public function test_deeply_nested_resolution(): void
    {
        $tracer = $this->createResolver();

        // Build a deeply nested infix tree: ((((1 + 2) + 3) + 4) + 5)
        $source = new StaticSource(1);
        for ($i = 2; $i <= 5; $i++) {
            $source = new InfixExpression($source, '+', new StaticSource($i));
        }

        $traced = $tracer->traced($source);

        // Verify we got a deep tree without stack issues
        $this->assertTrue($traced->result->isOk());

        // Count total nodes by collecting 'outcome' metadata
        $outcomes = $traced->trace->collect('outcome');
        // 4 InfixExpressions + 5 StaticSources = 9 nodes
        $this->assertCount(9, $outcomes);
    }

    // ---------------------------------------------------------------
    // Generic metadata tests
    // ---------------------------------------------------------------

    public function test_every_node_has_duration_outcome_has_value(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new InfixExpression(
                new StaticSource(10),
                '+',
                new StaticSource(20),
            )
        );

        $this->assertNodeHasGenericMetadata($traced->trace);

        foreach ($traced->trace->children() as $child) {
            $this->assertNodeHasGenericMetadata($child);
        }
    }

    public function test_successful_resolution_has_value_metadata(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(new StaticSource(42));

        $this->assertSame('ok', $traced->trace->getMetadata('outcome'));
        $this->assertTrue($traced->trace->getMetadata('has_value'));
        $this->assertSame(42, $traced->trace->getMetadata('value'));
    }

    public function test_none_resolution_has_value_false(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(new StaticSource(null));

        $this->assertSame('ok', $traced->trace->getMetadata('outcome'));
        $this->assertFalse($traced->trace->getMetadata('has_value'));
        $this->assertNull($traced->trace->getMetadata('value'));
    }

    public function test_object_values_are_stored_as_class_name(): void
    {
        $tracer = $this->createResolver();
        $object = new \stdClass();

        $traced = $tracer->traced(new StaticSource($object));

        $this->assertSame(\stdClass::class, $traced->trace->getMetadata('value'));
    }

    public function test_duration_is_non_negative(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(new StaticSource(1));

        $duration = $traced->trace->getMetadata('duration_ms');
        $this->assertIsFloat($duration);
        $this->assertGreaterThanOrEqual(0, $duration);
    }

    public function test_timestamp_is_a_microtime_float(): void
    {
        $before = microtime(true);
        $tracer = $this->createResolver();

        $traced = $tracer->traced(new StaticSource(1));
        $after = microtime(true);

        $timestamp = $traced->trace->getMetadata('timestamp');
        $this->assertIsFloat($timestamp);
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function test_error_resolution_has_error_metadata(): void
    {
        $tracer = $this->createResolver();

        // Coercing a non-numeric string to NumberType should fail
        $traced = $tracer->traced(
            new TypeDefinition(new NumberType(), new StaticSource('not a number'))
        );

        $root = $traced->trace;
        $this->assertSame('err', $root->getMetadata('outcome'));
        $this->assertNotNull($root->getMetadata('error'));
    }

    // ---------------------------------------------------------------
    // Resolver annotation tests
    // ---------------------------------------------------------------

    public function test_resolver_annotations_appear_on_correct_node(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(new StaticSource(42));

        // StaticResolver annotates label as 'static(int)'
        $this->assertSame('static(int)', $traced->trace->label());
    }

    public function test_annotations_at_correct_depth_in_tree(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new InfixExpression(
                new StaticSource(10),
                '+',
                new StaticSource(20),
            )
        );

        // InfixResolver annotates with operator
        $this->assertSame('+', $traced->trace->label());

        // Children are StaticSources with their own labels
        $this->assertSame('static(int)', $traced->trace->children()[0]->label());
        $this->assertSame('static(int)', $traced->trace->children()[1]->label());
    }

    public function test_annotations_dont_leak_to_siblings(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new InfixExpression(
                new StaticSource(10),
                '+',
                new StaticSource(20),
            )
        );

        $left = $traced->trace->children()[0];
        $right = $traced->trace->children()[1];

        // Each child has its own label, not the other's
        $this->assertSame('static(int)', $left->label());
        $this->assertSame('static(int)', $right->label());

        // Values are distinct
        $this->assertSame(10, $left->getMetadata('value'));
        $this->assertSame(20, $right->getMetadata('value'));
    }

    public function test_annotations_dont_leak_to_parent(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new TypeDefinition(
                new NumberType(),
                new StaticSource(42),
            )
        );

        $root = $traced->trace;
        $child = $root->children()[0];

        // The child's label should not appear on the root
        $this->assertNotSame($child->label(), $root->label());
    }

    public function test_value_resolver_annotates_label(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new TypeDefinition(new NumberType(), new StaticSource(42))
        );

        $this->assertSame('NumberType', $traced->trace->label());
    }

    public function test_symbol_resolver_annotates_label(): void
    {
        $tracer = $this->createResolver([
            'base_rate' => new StaticSource(100),
        ]);

        $traced = $tracer->traced(new SymbolSource('base_rate'));

        $this->assertSame('base_rate', $traced->trace->label());
    }

    public function test_infix_resolver_annotates_operator(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new InfixExpression(
                new StaticSource(2),
                '*',
                new StaticSource(3),
            )
        );

        $this->assertSame('*', $traced->trace->label());
    }

    // ---------------------------------------------------------------
    // Callback tests
    // ---------------------------------------------------------------

    public function test_on_trace_fires_once_per_top_level_resolve(): void
    {
        $tracer = $this->createResolver();

        $traces = [];
        $tracer->onTrace(function (TracedResult $traced) use (&$traces): void {
            $traces[] = $traced;
        });

        $tracer->resolve(new StaticSource(1));
        $tracer->resolve(new StaticSource(2));

        $this->assertCount(2, $traces);
    }

    public function test_on_trace_does_not_fire_for_inner_resolutions(): void
    {
        $tracer = $this->createResolver();

        $callCount = 0;
        $tracer->onTrace(function (TracedResult $traced) use (&$callCount): void {
            $callCount++;
        });

        // InfixExpression triggers recursive resolution of left and right,
        // but onTrace should only fire once for the top-level call
        $tracer->resolve(
            new InfixExpression(
                new StaticSource(1),
                '+',
                new StaticSource(2),
            )
        );

        $this->assertSame(1, $callCount);
    }

    public function test_on_trace_receives_complete_trace_tree(): void
    {
        $tracer = $this->createResolver();

        $captured = null;
        $tracer->onTrace(function (TracedResult $traced) use (&$captured): void {
            $captured = $traced;
        });

        $tracer->resolve(
            new InfixExpression(
                new StaticSource(10),
                '+',
                new StaticSource(20),
            )
        );

        $this->assertNotNull($captured);
        $this->assertSame(InfixExpression::class, $captured->trace->sourceType);
        $this->assertCount(2, $captured->trace->children());
        $this->assertTrue($captured->result->isOk());
    }

    public function test_traced_returns_correct_traced_result(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(new StaticSource(42));

        $this->assertInstanceOf(TracedResult::class, $traced);
        $this->assertTrue($traced->result->isOk());
        $this->assertSame(42, $traced->result->unwrap()->unwrap());
        $this->assertSame(StaticSource::class, $traced->trace->sourceType);
    }

    public function test_traced_does_not_interfere_with_existing_on_trace(): void
    {
        $tracer = $this->createResolver();

        $callbackFired = false;
        $tracer->onTrace(function (TracedResult $traced) use (&$callbackFired): void {
            $callbackFired = true;
        });

        // traced() temporarily replaces the callback
        $tracer->traced(new StaticSource(1));

        // The original callback should be restored
        $tracer->resolve(new StaticSource(2));
        $this->assertTrue($callbackFired);
    }

    // ---------------------------------------------------------------
    // Result correctness tests
    // ---------------------------------------------------------------

    public function test_resolve_returns_correct_result_for_static(): void
    {
        $tracer = $this->createResolver();

        $result = $tracer->resolve(new StaticSource(42));

        $this->assertTrue($result->isOk());
        $this->assertSame(42, $result->unwrap()->unwrap());
    }

    public function test_resolve_returns_correct_result_for_infix(): void
    {
        $tracer = $this->createResolver();

        $result = $tracer->resolve(
            new InfixExpression(
                new StaticSource(10),
                '+',
                new StaticSource(20),
            )
        );

        $this->assertTrue($result->isOk());
        $this->assertSame(30, $result->unwrap()->unwrap());
    }

    public function test_resolve_returns_correct_result_for_type_definition(): void
    {
        $tracer = $this->createResolver();

        $result = $tracer->resolve(
            new TypeDefinition(new NumberType(), new StaticSource(42))
        );

        $this->assertTrue($result->isOk());
        $this->assertSame(42, $result->unwrap()->unwrap());
    }

    public function test_resolve_returns_correct_result_for_symbol(): void
    {
        $tracer = $this->createResolver([
            'price' => new StaticSource(99),
        ]);

        $result = $tracer->resolve(new SymbolSource('price'));

        $this->assertTrue($result->isOk());
        $this->assertSame(99, $result->unwrap()->unwrap());
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function test_resolution_that_throws(): void
    {
        $delegating = new DelegatingResolver([
            StaticSource::class => StaticResolver::class,
        ]);

        $tracer = new TracingResolver($delegating);

        // Resolving a source type with no registered resolver should throw
        $this->expectException(RuntimeException::class);
        $tracer->resolve(new SymbolSource('unknown'));
    }

    public function test_single_node_tree(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(new StaticSource(1));

        $this->assertSame([], $traced->trace->children());
        $this->assertSame('ok', $traced->trace->getMetadata('outcome'));
    }

    public function test_collect_across_traced_tree(): void
    {
        $tracer = $this->createResolver();

        $traced = $tracer->traced(
            new InfixExpression(
                new StaticSource(10),
                '+',
                new StaticSource(20),
            )
        );

        // Collect all labels from the tree
        $labels = $traced->trace->collect('label');
        $this->assertCount(3, $labels); // infix operator + 2 static labels
        $this->assertContains('+', $labels);
    }

    public function test_get_delegates_to_inner_resolver(): void
    {
        $tracer = $this->createResolver();

        $this->assertInstanceOf(TracingResolver::class, $tracer->get(Resolver::class));
    }

    public function test_has_delegates_to_inner_resolver(): void
    {
        $tracer = $this->createResolver();

        $this->assertTrue($tracer->has(Resolver::class));
        $this->assertFalse($tracer->has('non.existent.key'));
    }

    public function test_multiple_sequential_resolutions_produce_independent_trees(): void
    {
        $tracer = $this->createResolver();

        $traced1 = $tracer->traced(new StaticSource(1));
        $traced2 = $tracer->traced(new StaticSource(2));

        $this->assertSame(1, $traced1->trace->getMetadata('value'));
        $this->assertSame(2, $traced2->trace->getMetadata('value'));
        $this->assertNotSame($traced1->trace, $traced2->trace);
    }

    private function assertNodeHasGenericMetadata(ResolutionTrace $node): void
    {
        $meta = $node->metadata();
        $this->assertArrayHasKey('timestamp', $meta, "Node {$node->sourceType} missing timestamp");
        $this->assertArrayHasKey('duration_ms', $meta, "Node {$node->sourceType} missing duration_ms");
        $this->assertArrayHasKey('outcome', $meta, "Node {$node->sourceType} missing outcome");
        $this->assertArrayHasKey('has_value', $meta, "Node {$node->sourceType} missing has_value");
    }
}
