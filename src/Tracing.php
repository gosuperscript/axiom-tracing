<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

use Superscript\Axiom\Resolvers\BindableResolver;

final class Tracing
{
    /**
     * Wrap a BindableResolver with tracing if enabled.
     * If disabled, returns the resolver untouched — no decorator,
     * no bindings, no overhead.
     */
    public static function wrap(
        BindableResolver $resolver,
        bool $enabled = false,
    ): BindableResolver {
        if (! $enabled) {
            return $resolver;
        }

        return new TracingResolver($resolver);
    }
}
