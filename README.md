# axiom-tracing

Opt-in resolution tracing for [gosuperscript/axiom](https://github.com/gosuperscript/axiom). Decorates `DelegatingResolver` to build a tree-shaped trace that mirrors the recursive resolution call stack, capturing timing, outcomes, and resolver-contributed metadata on each node.

## Installation

```bash
composer require gosuperscript/axiom-tracing
```

## Usage

### Conditional tracing with callback

```php
use Superscript\Axiom\Resolvers\DelegatingResolver;
use Superscript\Axiom\Tracing\Tracing;
use Superscript\Axiom\Tracing\TracedResult;
use Superscript\Axiom\Tracing\TracingResolver;

$resolver = Tracing::wrap(
    new DelegatingResolver([...]),
    enabled: config('axiom.tracing', false),
);

// Register a callback — fires on every top-level resolution
if ($resolver instanceof TracingResolver) {
    $resolver->onTrace(function (TracedResult $traced) {
        Log::debug('Axiom resolution', ['trace' => $traced->dump()]);
    });
}

// Calling code is unchanged
$result = $resolver->resolve($source);
```

### Explicit trace retrieval

```php
use Superscript\Axiom\Tracing\TracingResolver;

$tracer = new TracingResolver($delegatingResolver);

$traced = $tracer->traced($source);
$traced->result;  // Result<Option<T>>
$traced->trace;   // ResolutionTrace tree
$traced->dump();  // formatted string
```

### Extracting metadata from a trace

```php
// Collect all values for a key across the entire tree
$httpExchanges = $traced->trace->collect('http_response');

// Walk the tree manually
foreach ($traced->trace->children() as $child) {
    $child->getMetadata('http_response');
}
```

### Production use (flat inspector, no tracing)

For extracting resolver metadata without full tracing overhead:

```php
use Superscript\Axiom\Tracing\ResolutionContext;
use Superscript\Axiom\ResolutionInspector;

$context = new ResolutionContext();
$resolver->instance(ResolutionInspector::class, $context);

$result = $resolver->resolve($source);
$httpResponse = $context->get('http_response');
```

## Components

| Component | Purpose |
|---|---|
| `ResolutionContext` | Flat key-value `ResolutionInspector` for production metadata extraction |
| `ResolutionTrace` | Tree node with children, metadata, and recursive `collect()` |
| `TracingResolutionInspector` | Tree-aware inspector routing annotations to the current trace node |
| `TracingResolver` | Decorator intercepting every `resolve()` call to build the trace tree |
| `TracedResult` | Pairs a `Result` with its `ResolutionTrace` tree |
| `TraceFormatter` | Renders trace trees as human-readable indented strings |
| `Tracing` | Factory with `wrap()` for conditional decoration |

## Example output

```
TypeDefinition [NumberType] — ok, value: 150, 52ms
└── InfixExpression [*] — ok, value: 150, 51ms
    ├── SymbolSource [base_rate] — ok, value: 100, 3ms
    │   └── StaticSource [static(int)] — ok, value: 100, 0.01ms
    └── InfixExpression [+] — ok, value: 1.5, 45ms
        ├── StaticSource [static(float)] — ok, value: 0.5, 0.01ms
        └── StaticSource [static(int)] — ok, value: 1, 0.01ms
```

## Testing

```bash
composer install
vendor/bin/phpunit
```
