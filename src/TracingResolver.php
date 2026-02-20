<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Axiom\ResolutionInspector;
use Superscript\Axiom\Resolvers\BindableResolver;
use Superscript\Axiom\Resolvers\Resolver;
use Superscript\Axiom\Source;
use Superscript\Monads\Result\Result;

final class TracingResolver implements BindableResolver
{
    private ?ResolutionTrace $root = null;
    private int $depth = 0;
    private TracingResolutionInspector $inspector;

    /** @var null|callable(TracedResult): void */
    private $onTrace = null;

    public function __construct(
        private readonly BindableResolver $inner,
    ) {
        $this->inspector = new TracingResolutionInspector();

        // Replace the Resolver binding so all recursive resolution
        // flows through this decorator.
        //
        // DelegatingResolver::resolve() does NOT go through the IoC
        // Resolver binding for its own dispatch — it calls
        // $this->container->make($resolverClass)->resolve($source)
        // directly. The IoC Resolver binding is only injected into
        // child resolvers (InfixResolver, ValueResolver, etc.) when
        // they are constructed via the container. Those resolvers then
        // call $this->resolver->resolve() for recursive resolution,
        // which hits this TracingResolver. No infinite recursion.
        $this->inner->instance(Resolver::class, $this);

        // Replace any existing ResolutionInspector with our tree-aware
        // version. Resolver annotations now land on trace nodes.
        $this->inner->instance(ResolutionInspector::class, $this->inspector);
    }

    /** @param class-string $key */
    public function instance(string $key, mixed $concrete): void
    {
        $this->inner->instance($key, $concrete);
    }

    /**
     * Register a callback that fires whenever a top-level resolution
     * completes. This is how you consume traces without changing
     * calling code.
     *
     * @param callable(TracedResult): void $callback
     */
    public function onTrace(callable $callback): void
    {
        $this->onTrace = $callback;
    }

    public function resolve(Source $source): Result
    {
        $isRoot = $this->depth === 0;

        if ($isRoot) {
            $this->root = null;
        }

        $this->depth++;

        // Create a trace node for this resolution
        $node = new ResolutionTrace(
            sourceType: $source::class,
        );

        // Link into the tree
        $parent = $this->inspector->current();

        if ($parent !== null) {
            $parent->addChild($node);
        } else {
            $this->root = $node;
        }

        // Set as current — resolver annotations and child resolutions
        // will attach to this node
        $this->inspector->setCurrent($node);

        // Delegate to the real resolver chain.
        // $this->inner->resolve($source) is a direct method call on
        // the DelegatingResolver instance — it does NOT go through
        // the IoC binding (which points back to us). The IoC binding
        // is only used when child resolvers internally call
        // $this->resolver->resolve() for recursive resolution.
        $start = microtime(true);
        $result = $this->inner->resolve($source);
        $duration = (microtime(true) - $start) * 1000;

        // Generic metadata — captured for every node automatically
        $node->addMetadata('timestamp', $start);
        $node->addMetadata('duration_ms', round($duration, 3));
        $node->addMetadata('outcome', $result->isOk() ? 'ok' : 'err');

        if ($result->isOk()) {
            $option = $result->unwrap();
            $node->addMetadata('has_value', $option->isSome());

            if ($option->isSome()) {
                $value = $option->unwrap();
                // Store scalar values directly, objects by class name
                $node->addMetadata('value', is_object($value)
                    ? $value::class
                    : $value
                );
            }
        } else {
            $error = $result->unwrapErr();
            $node->addMetadata('error', $error instanceof \Throwable
                ? $error->getMessage()
                : (string) $error
            );
        }

        // Restore parent as current so siblings attach to the same parent
        $this->inspector->setCurrent($parent);

        $this->depth--;

        // Emit the trace when the root resolution completes
        if ($isRoot && $this->onTrace !== null && $this->root !== null) {
            ($this->onTrace)(new TracedResult($result, $this->root));
        }

        return $result;
    }

    /**
     * Explicit entry point for when you want the trace returned directly.
     */
    public function traced(Source $source): TracedResult
    {
        // Temporarily capture the trace via onTrace
        $captured = null;
        $previousCallback = $this->onTrace;

        $this->onTrace = function (TracedResult $traced) use (&$captured): void {
            $captured = $traced;
        };

        $result = $this->resolve($source);

        $this->onTrace = $previousCallback;

        // If depth tracking worked correctly, $captured should be set.
        // Fall back to constructing from root if somehow not.
        return $captured ?? new TracedResult($result, $this->root ?? new ResolutionTrace('unknown'));
    }
}
