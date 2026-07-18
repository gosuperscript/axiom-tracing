# axiom-tracing

Opt-in execution tracing for [gosuperscript/axiom](https://github.com/gosuperscript/axiom).

The package runs a compiled `Program` with an invocation-scoped observer and turns Axiom's ordered node lifecycle into a trace tree. Every evaluated source node records its source class, certified return type, annotations, outcome, value or error, and duration.

## Requirements

- PHP ^8.4
- gosuperscript/axiom ^0.6
- gosuperscript/monads ^1.0

## Installation

```bash
composer require gosuperscript/axiom-tracing
```

## Usage

Compile normally, then pass the `Program` to `Tracing::run()`:

```php
use Superscript\Axiom\Definitions;
use Superscript\Axiom\Expression;
use Superscript\Axiom\Sources\Coerce;
use Superscript\Axiom\Sources\InfixExpression;
use Superscript\Axiom\Sources\StaticSource;
use Superscript\Axiom\Sources\SymbolSource;
use Superscript\Axiom\Tracing\Tracing;
use Superscript\Axiom\Types\NumberType;

$program = (new Expression(
    source: new InfixExpression(
        new SymbolSource('base'),
        '*',
        new Coerce(new NumberType(), new StaticSource('4')),
    ),
    definitions: new Definitions(['base' => new StaticSource(3)]),
))->compile()->unwrap();

$traced = Tracing::run($program);

$traced->result->unwrap()->unwrap(); // 12
echo $traced->dump();
```

The dump is a real evaluation tree, not a grouped annotation log:

```text
InfixExpression [*] — ok, value: 12, 0.1ms
    left: 3
    right: 4
    result: 12
    ├── SymbolSource [base] — ok, value: 3, 0.02ms
    │   memo: miss
    │   result: 3
    │   └── StaticSource [static(int)] — ok, value: 3, 0.005ms
    └── Coerce [Number] — ok, value: 4, 0.02ms
        coercion: string -> int
        └── StaticSource [static(string)] — ok, value: 4, 0.005ms
```

Durations vary by invocation.

Bindings are the optional second argument:

```php
$traced = Tracing::run($program, ['amount' => '12']);
```

## API

| Component | Purpose |
|---|---|
| `Tracing::run(Program, bindings)` | Runs one invocation and returns its result with a fresh trace |
| `TracedResult` | Pairs the unchanged Axiom `Result` with the root `ExecutionTrace` |
| `ExecutionTrace` | One source node: children, ordered annotations, timing, outcome, value, and error |
| `TraceCollector` | The reusable low-level implementation of Axiom's `Execution\Observer` |
| `TraceFormatter` | Renders an `ExecutionTrace` as a readable tree |

Repeated annotations retain their emission order:

```php
$trace->get('label');       // last value for this node
$trace->all('result');      // every result annotation on this node
$trace->collect('result');  // result annotations from the whole subtree
$trace->annotations();      // globally ordered within this node
$trace->children();
```

The generic lifecycle data has dedicated accessors: `outcome()`, `durationMs()`, `hasValue()`, `value()`, and `error()`. The underlying Axiom node descriptor is available as `$trace->node`, including `$trace->node->sourceType` and `$trace->node->returns`.

## Invocation scope

Tracing state is never attached to a serializable `Source`, an `Expression`, or a compiled `Program`. `Tracing::run()` creates a fresh collector and passes it only to that call, so sequential or concurrent invocations cannot share a trace accidentally.

A boundary admission error happens before compiled source evaluation begins. It is represented as a single `Program` trace node with an `err` outcome. A host exception is still rethrown, preserving `Program` semantics. To inspect such a partial trace, use the collector directly:

```php
use Superscript\Axiom\Tracing\TraceCollector;

$collector = new TraceCollector();

try {
    $program->call($bindings, observer: $collector);
} finally {
    $partialTrace = $collector->trace();
}
```

## Extension annotations

Core and host source compilers annotate the node currently being evaluated through `SourceEvaluation::annotate()`:

```php
use Superscript\Axiom\CompiledSource;
use Superscript\Axiom\SourceCompilation;
use Superscript\Axiom\SourceEvaluation;

private function compile(MySource $source, SourceCompilation $compilation): CompiledSource
{
    return $compilation->custom($returnType, function (SourceEvaluation $evaluation) use ($service) {
        $value = $service->lookup();
        $evaluation->annotate('cache', 'miss');
        $evaluation->annotate('result', $value);

        return $value;
    });
}
```

Because Axiom wraps every `CompiledSource` with the source identity at compile time, host source compilers participate automatically; they do not need tracing-specific integration.
