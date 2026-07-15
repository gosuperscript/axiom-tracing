<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

/**
 * Renders the flat annotation log a compiled program emits during evaluation
 * — the {@see ResolutionContext} store, as returned by `all()`/`flush()` —
 * into a readable, order-preserving list.
 *
 * The log is `array<string, list<mixed>>`: keys in the order they were first
 * annotated, and each key's values in the order they were recorded. One line
 * is produced per recorded value:
 *
 * ```
 * memo: miss
 * label: base
 * result: 3
 * label: static(int)
 * ```
 *
 * Value rendering: booleans as `true`/`false`, strings verbatim (unquoted),
 * everything else json-encoded.
 */
final class TraceFormatter
{
    /**
     * @param array<string, list<mixed>> $log
     */
    public function format(array $log): string
    {
        $lines = [];

        foreach ($log as $key => $values) {
            foreach ($values as $value) {
                $lines[] = $key . ': ' . $this->render($value);
            }
        }

        return implode("\n", $lines);
    }

    private function render(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES),
        };
    }
}
