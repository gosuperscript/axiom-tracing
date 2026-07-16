<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Closure;
use LogicException;
use Superscript\Axiom\Execution\Annotated;
use Superscript\Axiom\Execution\Entered;
use Superscript\Axiom\Execution\Event;
use Superscript\Axiom\Execution\Exited;
use Superscript\Axiom\Execution\Observer;
use Superscript\Axiom\Execution\Threw;

final class TraceCollector implements Observer
{
    /** @var list<ExecutionTrace> */
    private array $stack = [];

    private ?ExecutionTrace $root = null;

    /** @var Closure(): float */
    private Closure $clock;

    /** @param null|Closure(): float $clock */
    public function __construct(?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    public function observe(Event $event): void
    {
        match (true) {
            $event instanceof Entered => $this->entered($event),
            $event instanceof Annotated => $this->annotated($event),
            $event instanceof Exited => $this->exited($event),
            $event instanceof Threw => $this->threw($event),
            default => null,
        };
    }

    public function trace(): ?ExecutionTrace
    {
        return $this->root;
    }

    private function entered(Entered $event): void
    {
        $trace = new ExecutionTrace($event->node, ($this->clock)());
        $parent = $this->current();

        if ($parent === null) {
            if ($this->root !== null) {
                throw new LogicException('An execution trace can have only one root node.');
            }

            $this->root = $trace;
        } else {
            $parent->addChild($trace);
        }

        $this->stack[] = $trace;
    }

    private function annotated(Annotated $event): void
    {
        $this->currentFor($event)->annotate($event->key, $event->value);
    }

    private function exited(Exited $event): void
    {
        $this->currentFor($event)->complete($event->result, ($this->clock)());
        array_pop($this->stack);
    }

    private function threw(Threw $event): void
    {
        $this->currentFor($event)->threw($event->exception, ($this->clock)());
        array_pop($this->stack);
    }

    private function current(): ?ExecutionTrace
    {
        $index = array_key_last($this->stack);

        return $index === null ? null : $this->stack[$index];
    }

    private function currentFor(Event $event): ExecutionTrace
    {
        $current = $this->current();

        if ($current === null || $current->node !== $event->node) {
            throw new LogicException('Execution events must be balanced and ordered by node.');
        }

        return $current;
    }
}
