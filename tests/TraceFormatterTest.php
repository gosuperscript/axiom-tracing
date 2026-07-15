<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Tracing\TraceFormatter;

#[CoversClass(TraceFormatter::class)]
final class TraceFormatterTest extends TestCase
{
    private TraceFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TraceFormatter();
    }

    public function test_empty_log_renders_empty_string(): void
    {
        $this->assertSame('', $this->formatter->format([]));
    }

    public function test_one_line_per_recorded_value_preserving_order(): void
    {
        $log = [
            'memo' => ['miss'],
            'label' => ['base', 'static(int)'],
            'result' => [3],
        ];

        $expected = implode("\n", [
            'memo: miss',
            'label: base',
            'label: static(int)',
            'result: 3',
        ]);

        $this->assertSame($expected, $this->formatter->format($log));
    }

    public function test_booleans_render_as_true_and_false(): void
    {
        $output = $this->formatter->format(['subject' => [true, false]]);

        $this->assertSame("subject: true\nsubject: false", $output);
    }

    public function test_strings_render_verbatim_without_quotes(): void
    {
        $output = $this->formatter->format(['coercion' => ['string -> int']]);

        $this->assertSame('coercion: string -> int', $output);
    }

    public function test_non_string_scalars_are_json_encoded(): void
    {
        $output = $this->formatter->format([
            'matched_arm' => [0],
            'result' => [3.5],
        ]);

        $this->assertSame("matched_arm: 0\nresult: 3.5", $output);
    }

    public function test_arrays_are_json_encoded_with_unescaped_slashes(): void
    {
        $output = $this->formatter->format([
            'result' => [['status' => 200, 'path' => 'a/b']],
        ]);

        $this->assertSame('result: {"status":200,"path":"a/b"}', $output);
    }
}
