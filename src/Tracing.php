<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Axiom\Resolvers\DelegatingResolver;

final class Tracing
{
    /**
     * Wrap a DelegatingResolver with tracing if enabled.
     * If disabled, returns the resolver untouched — no decorator,
     * no bindings, no overhead.
     */
    public static function wrap(
        DelegatingResolver $resolver,
        bool $enabled = false,
    ): DelegatingResolver|TracingResolver {
        if (! $enabled) {
            return $resolver;
        }

        return new TracingResolver($resolver);
    }
}
