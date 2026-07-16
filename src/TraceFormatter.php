<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing;

final class TraceFormatter
{
    public function format(ExecutionTrace $trace): string
    {
        return implode("\n", $this->lines($trace));
    }

    /** @return list<string> */
    private function lines(ExecutionTrace $trace, string $prefix = '', ?bool $last = null): array
    {
        $connector = match ($last) {
            null => '',
            true => '└── ',
            false => '├── ',
        };
        $lines = [$prefix.$connector.$this->summary($trace)];
        $indent = $prefix.match ($last) {
            false => '│   ',
            default => '    ',
        };

        foreach ($trace->annotations() as $annotation) {
            if ($annotation['key'] === 'label') {
                continue;
            }

            $lines[] = $indent.$annotation['key'].': '.$this->display($annotation['value']);
        }

        $children = $trace->children();

        foreach ($children as $index => $child) {
            $lines = [
                ...$lines,
                ...$this->lines($child, $indent, $index === array_key_last($children)),
            ];
        }

        return $lines;
    }

    private function summary(ExecutionTrace $trace): string
    {
        $summary = $this->shortName($trace->node->sourceType);
        $label = $trace->label();

        if ($label !== null) {
            $summary .= " [{$label}]";
        }

        $details = array_filter([
            $trace->outcome(),
            $trace->hasValue() ? 'value: '.$this->display($trace->value()) : null,
            $trace->error() === null ? null : 'error: '.$trace->error(),
            $trace->durationMs() === null ? null : $this->duration($trace->durationMs()),
        ], static fn (?string $detail): bool => $detail !== null);

        return $details === [] ? $summary : $summary.' — '.implode(', ', $details);
    }

    private function display(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value), is_int($value), is_float($value) => (string) $value,
            default => $this->encode($value),
        };
    }

    private function encode(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);

        return $encoded === false ? get_debug_type($value) : $encoded;
    }

    private function duration(float $duration): string
    {
        return ($duration >= 1 ? round($duration) : round($duration, 3)).'ms';
    }

    private function shortName(string $class): string
    {
        $segments = explode('\\', $class);

        return end($segments);
    }
}
