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
        $line .= $this->buildLabel($node);
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

    /**
     * Build the bracketed label. For infix-like nodes (2 children, both
     * with scalar values), show the expression: [left operator right].
     */
    private function buildLabel(ResolutionTrace $node): string
    {
        $label = $node->label();

        if ($label === null) {
            return '';
        }

        $children = $node->children();

        if (count($children) === 2) {
            $leftValue = $children[0]->getMetadata('value');
            $rightValue = $children[1]->getMetadata('value');

            if (is_scalar($leftValue) && is_scalar($rightValue)) {
                $left = $this->displayValue($leftValue);
                $right = $this->displayValue($rightValue);

                return " [{$left} {$label} {$right}]";
            }
        }

        return " [{$label}]";
    }

    private function formatSummary(ResolutionTrace $node): string
    {
        $parts = [];
        $meta = $node->metadata();

        if (isset($meta['outcome'])) {
            $parts[] = $meta['outcome'];
        }

        if (isset($meta['value'])) {
            $parts[] = "value: {$this->displayValue($meta['value'])}";
        }

        if (isset($meta['error'])) {
            $parts[] = "error: {$meta['error']}";
        }

        if (isset($meta['duration_ms'])) {
            $parts[] = "{$meta['duration_ms']}ms";
        }

        return $parts !== [] ? ' — ' . implode(', ', $parts) : '';
    }

    private function displayValue(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value)
            ? (string) $value
            : get_debug_type($value);
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
