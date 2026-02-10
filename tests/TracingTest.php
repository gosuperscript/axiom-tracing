<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Resolvers\DelegatingResolver;
use Superscript\Axiom\Resolvers\StaticResolver;
use Superscript\Axiom\Sources\StaticSource;
use Superscript\Axiom\Tracing\Tracing;
use Superscript\Axiom\Tracing\TracingResolver;

final class TracingTest extends TestCase
{
    public function test_wrap_with_enabled_false_returns_original_resolver(): void
    {
        $delegating = new DelegatingResolver([
            StaticSource::class => StaticResolver::class,
        ]);

        $result = Tracing::wrap($delegating, enabled: false);

        $this->assertSame($delegating, $result);
        $this->assertNotInstanceOf(TracingResolver::class, $result);
    }

    public function test_wrap_with_enabled_true_returns_tracing_resolver(): void
    {
        $delegating = new DelegatingResolver([
            StaticSource::class => StaticResolver::class,
        ]);

        $result = Tracing::wrap($delegating, enabled: true);

        $this->assertInstanceOf(TracingResolver::class, $result);
    }

    public function test_wrap_default_is_disabled(): void
    {
        $delegating = new DelegatingResolver([
            StaticSource::class => StaticResolver::class,
        ]);

        $result = Tracing::wrap($delegating);

        $this->assertSame($delegating, $result);
    }

    public function test_zero_overhead_when_disabled(): void
    {
        $delegating = new DelegatingResolver([
            StaticSource::class => StaticResolver::class,
        ]);

        $resolver = Tracing::wrap($delegating, enabled: false);

        // Should work normally — no tracing overhead
        $result = $resolver->resolve(new StaticSource(42));

        $this->assertTrue($result->isOk());
        $this->assertSame(42, $result->unwrap()->unwrap());
    }
}
