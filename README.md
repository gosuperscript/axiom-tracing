# axiom-tracing

Opt-in resolution tracing for [gosuperscript/axiom](https://github.com/gosuperscript/axiom). Collects the flat annotation log a compiled Axiom `Program` emits while it evaluates, and renders it as a readable list.

## What changed in this version

Axiom pivoted from a value-directed runtime to a compile-then-run typed model. There is no longer a `Resolver` interface, no `Context`, no PSR-11 container, and — crucially — **no per-node dispatch**: `Program::__invoke()` runs one precompiled closure, so there is no per-`Source` call boundary to intercept and no call tree to reconstruct.

The old resolver-interception tracer (`TracingResolver`, `Tracing::wrap`) depended on all of that and has been removed. The one observability primitive that survived the pivot is `ResolutionInspector::annotate(string $key, mixed $value): void`. A host attaches an inspector to an `Expression` before compiling; during evaluation, core's compiled nodes call `$runtime->inspector?->annotate(...)`. The calls are a **flat `(key, value)` stream** — not a tree.

This package is now that inspector, plus formatting.

## Requirements

- PHP ^8.4
- [gosuperscript/axiom](https://github.com/gosuperscript/axiom) ^0.5.0
- [gosuperscript/monads](https://github.com/gosuperscript/monads) ^1.0

## Installation

```bash
composer require gosuperscript/axiom-tracing
```

## Usage

Build an `Expression`, attach a `ResolutionContext` inspector with `->withInspector(...)`, compile, invoke the `Program`, then read the annotation log its evaluation emitted.

```php
use Superscript\Axiom\Definitions;
use Superscript\Axiom\Expression;
use Superscript\Axiom\Sources\Coerce;
use Superscript\Axiom\Sources\InfixExpression;
use Superscript\Axiom\Sources\StaticSource;
use Superscript\Axiom\Sources\SymbolSource;
use Superscript\Axiom\Tracing\ResolutionContext;
use Superscript\Axiom\Types\NumberType;

$context = new ResolutionContext();

// base * (int) '4', where `base` is a definition.
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

$result = $program(); // 12

// Read the annotation log the compiled program emitted.
$context->get('label');    // '*'          — last value recorded for a key
$context->all('memo');     // ['miss']     — every value for a key, in order
$context->get('coercion'); // 'string -> int'
```

`ResolutionContext` is a flat, multi-value store keyed by annotation name:

| Method | Purpose |
|---|---|
| `annotate($key, $value)` | Record a value (called by core during evaluation) |
| `get($key)` | The last value recorded for a key, or `null` |
| `all($key)` | Every value recorded for a key, in order |
| `flush()` | Return the whole log (`array<string, list<mixed>>`) and clear it |
| `reset()` | Clear the log without returning it |

Order is preserved **within each key** and across keys in first-seen order. The store groups by key, so it does not preserve the global interleaving of the emission stream — `all('result')` gives you every result in evaluation order, but results and labels are not interleaved with one another.

### Pairing a result with its log, and formatting

`TracedResult` pairs a program's `Result` with a snapshot of the log, and `dump()` renders it via `TraceFormatter`:

```php
use Superscript\Axiom\Tracing\TracedResult;

$traced = new TracedResult($result, $context->flush());

echo $traced->dump();
// memo: miss
// label: static(int)
// label: base
// label: static(string)
// label: Number
// label: *
// result: 3
// result: 12
// coercion: string -> int
// left: 3
// right: 4
```

`TraceFormatter` renders any log (`array<string, list<mixed>>`) as one `key: value` line per recorded value, grouped by key in first-seen order. Booleans render as `true`/`false`, strings verbatim, everything else json-encoded.

## Annotation keys emitted by core

The keys and values are core's, not this package's. As of Axiom 0.5.0 they include:

| Key | Emitted by | Example value |
|---|---|---|
| `memo` | definition slot memoization | `miss`, `hit` |
| `label` | most nodes | `base`, `*`, `static(int)`, `Number`, `match` |
| `result` | evaluated nodes | the produced value |
| `left`, `right` | infix operators | operand values |
| `coercion` | boundary coercion | `string -> int` |
| `subject`, `matched_arm` | match expressions | the subject value, the arm index |

## Components

| Component | Purpose |
|---|---|
| `ResolutionContext` | The `ResolutionInspector` a host attaches to an `Expression`; a flat, multi-value annotation store |
| `TraceFormatter` | Renders a flat annotation log as a readable, order-preserving list |
| `TracedResult` | Pairs a program's `Result` with a snapshot of the annotation log |
