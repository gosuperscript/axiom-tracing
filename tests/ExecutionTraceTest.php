<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Superscript\Axiom\Execution\Node;
use Superscript\Axiom\Tracing\ExecutionTrace;
use Superscript\Axiom\Types\NumberType;

use function Superscript\Monads\Option\None;
use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

final class ExecutionTraceTest extends TestCase
{
    public function test_it_preserves_ordered_repeated_annotations_and_tree_collection(): void
    {
        $root = new ExecutionTrace(new Node('Root', new NumberType), 10.0);
        $child = new ExecutionTrace(new Node('Child', new NumberType), 10.1);
        $root->annotate('label', 'root');
        $root->annotate('step', 1);
        $root->annotate('step', 2);
        $child->annotate('step', 3);
        $root->addChild($child);

        $this->assertSame('root', $root->label());
        $this->assertSame(2, $root->get('step'));
        $this->assertNull($root->get('missing'));
        $this->assertSame([1, 2], $root->all('step'));
        $this->assertSame([1, 2, 3], $root->collect('step'));
        $this->assertSame([$child], $root->children());
        $this->assertCount(3, $root->annotations());
    }

    public function test_it_records_success_absence_errors_and_throws(): void
    {
        $value = new ExecutionTrace(new Node('Value', new NumberType), 1.0);
        $value->complete(Ok(Some(new stdClass)), 1.0015);

        $this->assertSame('ok', $value->outcome());
        $this->assertTrue($value->hasValue());
        $this->assertSame(stdClass::class, $value->value());
        $this->assertSame(1.5, $value->durationMs());
        $this->assertNull($value->error());

        $absent = new ExecutionTrace(new Node('Absent', new NumberType), 2.0);
        $absent->complete(Ok(None()), 2.001);
        $this->assertFalse($absent->hasValue());
        $this->assertNull($absent->value());

        $error = new ExecutionTrace(new Node('Error', new NumberType), 3.0);
        $error->complete(Err(new RuntimeException('no value')), 3.002);
        $this->assertSame('err', $error->outcome());
        $this->assertSame('no value', $error->error());

        $threw = new ExecutionTrace(new Node('Threw', new NumberType), 4.0);
        $threw->threw(new RuntimeException('escaped'), 4.003);
        $this->assertSame('threw', $threw->outcome());
        $this->assertSame('escaped', $threw->error());
    }

    public function test_non_string_labels_are_not_presented_as_labels(): void
    {
        $trace = new ExecutionTrace(new Node('Source', new NumberType), 1.0);
        $trace->annotate('label', 42);

        $this->assertNull($trace->label());
        $this->assertNull($trace->outcome());
        $this->assertNull($trace->durationMs());
    }
}
