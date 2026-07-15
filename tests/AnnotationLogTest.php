<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Definitions;
use Superscript\Axiom\Expression;
use Superscript\Axiom\Sources\Coerce;
use Superscript\Axiom\Sources\InfixExpression;
use Superscript\Axiom\Sources\StaticSource;
use Superscript\Axiom\Sources\SymbolSource;
use Superscript\Axiom\Tracing\ResolutionContext;
use Superscript\Axiom\Tracing\TracedResult;
use Superscript\Axiom\Tracing\TraceFormatter;
use Superscript\Axiom\Types\NumberType;

/**
 * End-to-end: attach a {@see ResolutionContext} inspector to an
 * {@see Expression}, compile it, invoke the resulting Program, and read the
 * flat annotation log the compiled nodes emitted during evaluation. This is
 * the package's whole public story under the typesafe-Axiom pivot — there is
 * no resolver to decorate and no tree to reconstruct, only the flat
 * `(key, value)` stream core annotates through `Runtime::inspector`.
 *
 * Covered classes have their own unit tests; this exercises the seam.
 */
#[CoversNothing]
final class AnnotationLogTest extends TestCase
{
    #[Test]
    public function it_collects_the_annotations_a_compiled_program_emits(): void
    {
        $context = new ResolutionContext();

        // base * (int) '4' — base is a definition, the '4' coerces to int.
        $program = (new Expression(
            source: new InfixExpression(
                new SymbolSource('base'),
                '*',
                new Coerce(new NumberType(), new StaticSource('4')),
            ),
            definitions: new Definitions(['base' => new StaticSource(3)]),
        ))
            ->withInspector($context)
            ->compile()
            ->unwrap();

        $this->assertSame(12, $program()->unwrap()->unwrap());

        // The flat, order-preserving stream core emitted during evaluation.
        $this->assertSame('*', $context->get('label'));
        $this->assertSame(3, $context->get('left'));
        $this->assertSame(4, $context->get('right'));
        $this->assertSame(12, $context->get('result'));
        $this->assertSame('string -> int', $context->get('coercion'));
        $this->assertContains('base', $context->all('label'));
        $this->assertContains('miss', $context->all('memo'));
    }

    #[Test]
    public function it_records_memo_hits_when_a_definition_is_reused(): void
    {
        $context = new ResolutionContext();

        // base + base — the definition's slot is evaluated once, then reused.
        $program = (new Expression(
            source: new InfixExpression(
                new SymbolSource('base'),
                '+',
                new SymbolSource('base'),
            ),
            definitions: new Definitions(['base' => new StaticSource(5)]),
        ))
            ->withInspector($context)
            ->compile()
            ->unwrap();

        $result = $program();

        $this->assertSame(10, $result->unwrap()->unwrap());

        // Memoization surfaces as a miss on first read, a hit on reuse.
        $this->assertSame(['miss', 'hit'], $context->all('memo'));
        $this->assertSame([5, 5, 10], $context->all('result'));

        // flush() drains the store and pairs cleanly with the Result.
        $traced = new TracedResult($result, $context->flush());

        $this->assertSame($result, $traced->result);
        $this->assertSame([], $context->all('memo'), 'flush drained the store');

        // The formatted log is a readable, order-preserving list.
        $expected = (new TraceFormatter())->format($traced->log);
        $this->assertSame($expected, $traced->dump());
        $this->assertStringContainsString('memo: miss', $traced->dump());
        $this->assertStringContainsString('memo: hit', $traced->dump());
        $this->assertStringContainsString('result: 10', $traced->dump());
    }
}
