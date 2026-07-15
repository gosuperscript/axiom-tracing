<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Tracing\TracedResult;

use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\Ok;

#[CoversClass(TracedResult::class)]
final class TracedResultTest extends TestCase
{
    public function test_exposes_result_and_log(): void
    {
        $result = Ok(Some(42));
        $log = ['label' => ['static(int)'], 'result' => [42]];

        $traced = new TracedResult($result, $log);

        $this->assertSame($result, $traced->result);
        $this->assertSame($log, $traced->log);
    }

    public function test_dump_formats_the_flat_log(): void
    {
        $traced = new TracedResult(Ok(Some(42)), [
            'label' => ['static(int)'],
            'result' => [42],
        ]);

        $this->assertSame("label: static(int)\nresult: 42", $traced->dump());
    }
}
