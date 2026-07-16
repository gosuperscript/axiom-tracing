<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Expression;
use Superscript\Axiom\Program;
use Superscript\Axiom\Sources\InfixExpression;
use Superscript\Axiom\Sources\StaticSource;
use Superscript\Axiom\Sources\SymbolSource;
use Superscript\Axiom\Tracing\Tracing;
use Superscript\Axiom\Types\NumberType;

final class TracingTest extends TestCase
{
    public function test_run_returns_the_result_and_complete_execution_tree(): void
    {
        $program = (new Expression(new InfixExpression(
            new StaticSource(10),
            '+',
            new StaticSource(20),
        )))->compile()->unwrap();

        $traced = Tracing::run($program);

        $this->assertSame(30, $traced->result->unwrap()->unwrap());
        $this->assertSame(InfixExpression::class, $traced->trace->node->sourceType);
        $this->assertSame('+', $traced->trace->label());
        $this->assertSame('ok', $traced->trace->outcome());
        $this->assertSame(30, $traced->trace->value());
        $this->assertCount(2, $traced->trace->children());
        $this->assertSame(StaticSource::class, $traced->trace->children()[0]->node->sourceType);
        $this->assertSame(10, $traced->trace->children()[0]->value());
        $this->assertSame(20, $traced->trace->children()[1]->value());
    }

    public function test_each_run_gets_an_independent_collector(): void
    {
        $program = (new Expression(new StaticSource(1)))->compile()->unwrap();

        $first = Tracing::run($program);
        $second = Tracing::run($program);

        $this->assertNotSame($first->trace, $second->trace);
        $this->assertSame($first->result->unwrap()->unwrap(), $second->result->unwrap()->unwrap());
    }

    public function test_a_boundary_refusal_is_represented_before_evaluation_starts(): void
    {
        $program = (new Expression(
            new SymbolSource('amount'),
            declarations: ['amount' => new NumberType],
        ))->compile()->unwrap();

        $traced = Tracing::run($program);

        $this->assertTrue($traced->result->isErr());
        $this->assertSame(Program::class, $traced->trace->node->sourceType);
        $this->assertSame('err', $traced->trace->outcome());
        $this->assertNotNull($traced->trace->error());
        $this->assertStringContainsString('required input [amount] is missing', $traced->trace->error());
        $this->assertSame([], $traced->trace->children());
    }
}
