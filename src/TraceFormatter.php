<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

final class TraceFormatter
{
    public function format(ResolutionTrace $trace): string
    {
        return $this->formatNode($trace, '', true);
    }

    private function formatNode(
        ResolutionTrace $node,
        string $prefix,
        bool $isLast,
    ): string {
        $connector = $prefix === '' ? '' : ($isLast ? '└── ' : '├── ');

        // Build the main line: SourceType [label] — outcome, value, duration
        $line = $connector . $this->shortName($node->sourceType);

        if ($node->label() !== null) {
            $line .= " [{$node->label()}]";
        }

        $line .= $this->formatSummary($node);

        $lines = [$prefix . $line];

        // Add non-generic metadata (skip the ones already in the summary)
        $genericKeys = ['label', 'duration_ms', 'outcome', 'has_value', 'value', 'error'];

        foreach ($node->metadata() as $key => $value) {
            if (in_array($key, $genericKeys, true)) {
                continue;
            }

            $childPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $formatted = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES);
            $lines[] = $childPrefix . "{$key}: {$formatted}";
        }

        // Add children
        $children = $node->children();
        $childCount = count($children);

        foreach ($children as $i => $child) {
            $childPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $childIsLast = ($i === $childCount - 1);
            $lines[] = $this->formatNode($child, $childPrefix, $childIsLast);
        }

        return implode("\n", $lines);
    }

    private function formatSummary(ResolutionTrace $node): string
    {
        $parts = [];
        $meta = $node->metadata();

        if (isset($meta['outcome'])) {
            $parts[] = $meta['outcome'];
        }

        if (isset($meta['value'])) {
            $value = $meta['value'];
            $display = is_string($value) || is_int($value) || is_float($value)
                ? (string) $value
                : get_debug_type($value);
            $parts[] = "value: {$display}";
        }

        if (isset($meta['error'])) {
            $parts[] = "error: {$meta['error']}";
        }

        if (isset($meta['duration_ms'])) {
            $parts[] = "{$meta['duration_ms']}ms";
        }

        return $parts !== [] ? ' — ' . implode(', ', $parts) : '';
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
