<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Axiom\Execution\Node;
use Superscript\Monads\Option\Option;
use Superscript\Monads\Result\Result;
use Throwable;

final class ExecutionTrace
{
    /** @var list<self> */
    private array $children = [];

    /** @var list<array{key: string, value: mixed}> */
    private array $annotations = [];

    private ?float $durationMs = null;

    private ?string $outcome = null;

    private bool $hasValue = false;

    private mixed $value = null;

    private ?string $error = null;

    public function __construct(
        public readonly Node $node,
        public readonly float $timestamp,
    ) {}

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    public function annotate(string $key, mixed $value): void
    {
        $this->annotations[] = ['key' => $key, 'value' => $value];
    }

    /** @param Result<Option<mixed>, Throwable> $result */
    public function complete(Result $result, float $finishedAt): void
    {
        $this->durationMs = round(($finishedAt - $this->timestamp) * 1000, 3);
        $this->outcome = $result->isOk() ? 'ok' : 'err';

        if ($result->isErr()) {
            $this->error = $result->unwrapErr()->getMessage();

            return;
        }

        $option = $result->unwrap();
        $this->hasValue = $option->isSome();

        if ($option->isSome()) {
            $value = $option->unwrap();
            $this->value = is_object($value) ? $value::class : $value;
        }
    }

    public function threw(Throwable $exception, float $finishedAt): void
    {
        $this->durationMs = round(($finishedAt - $this->timestamp) * 1000, 3);
        $this->outcome = 'threw';
        $this->error = $exception->getMessage();
    }

    /** @return list<self> */
    public function children(): array
    {
        return $this->children;
    }

    /** @return list<array{key: string, value: mixed}> */
    public function annotations(): array
    {
        return $this->annotations;
    }

    public function label(): ?string
    {
        $label = $this->get('label');

        return is_string($label) ? $label : null;
    }

    public function get(string $key): mixed
    {
        for ($index = count($this->annotations) - 1; $index >= 0; $index--) {
            if ($this->annotations[$index]['key'] === $key) {
                return $this->annotations[$index]['value'];
            }
        }

        return null;
    }

    /** @return list<mixed> */
    public function all(string $key): array
    {
        $values = [];

        foreach ($this->annotations as $annotation) {
            if ($annotation['key'] === $key) {
                $values[] = $annotation['value'];
            }
        }

        return $values;
    }

    /** @return list<mixed> */
    public function collect(string $key): array
    {
        $values = $this->all($key);

        foreach ($this->children as $child) {
            $values = [...$values, ...$child->collect($key)];
        }

        return $values;
    }

    public function durationMs(): ?float
    {
        return $this->durationMs;
    }

    public function outcome(): ?string
    {
        return $this->outcome;
    }

    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function error(): ?string
    {
        return $this->error;
    }
}
