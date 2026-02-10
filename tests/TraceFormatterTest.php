<?php

declare(strict_types=1);

namespace Superscript\Axiom\Tracing\Tests;

use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Tracing\ResolutionTrace;
use Superscript\Axiom\Tracing\TraceFormatter;

final class TraceFormatterTest extends TestCase
{
    private TraceFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TraceFormatter();
    }

    public function test_single_node_with_label(): void
    {
        $node = new ResolutionTrace('App\\Sources\\StaticSource');
        $node->addMetadata('label', 'static(int)');
        $node->addMetadata('outcome', 'ok');
        $node->addMetadata('value', 42);
        $node->addMetadata('has_value', true);
        $node->addMetadata('duration_ms', 0.01);

        $output = $this->formatter->format($node);

        $this->assertStringContainsString('StaticSource', $output);
        $this->assertStringContainsString('[static(int)]', $output);
        $this->assertStringContainsString('ok', $output);
        $this->assertStringContainsString('value: 42', $output);
        $this->assertStringContainsString('0.01ms', $output);
    }

    public function test_single_node_without_label(): void
    {
        $node = new ResolutionTrace('App\\Sources\\StaticSource');
        $node->addMetadata('outcome', 'ok');
        $node->addMetadata('has_value', true);
        $node->addMetadata('value', 100);
        $node->addMetadata('duration_ms', 1.5);

        $output = $this->formatter->format($node);

        $this->assertStringContainsString('StaticSource', $output);
        $this->assertStringNotContainsString('[', $output);
    }

    public function test_tree_with_children_has_correct_branching(): void
    {
        $root = new ResolutionTrace('App\\InfixExpression');
        $root->addMetadata('label', '+');
        $root->addMetadata('outcome', 'ok');
        $root->addMetadata('value', 30);
        $root->addMetadata('has_value', true);
        $root->addMetadata('duration_ms', 5.0);

        $left = new ResolutionTrace('App\\StaticSource');
        $left->addMetadata('label', 'static(int)');
        $left->addMetadata('outcome', 'ok');
        $left->addMetadata('value', 10);
        $left->addMetadata('has_value', true);
        $left->addMetadata('duration_ms', 0.01);

        $right = new ResolutionTrace('App\\StaticSource');
        $right->addMetadata('label', 'static(int)');
        $right->addMetadata('outcome', 'ok');
        $right->addMetadata('value', 20);
        $right->addMetadata('has_value', true);
        $right->addMetadata('duration_ms', 0.01);

        $root->addChild($left);
        $root->addChild($right);

        $output = $this->formatter->format($root);

        $this->assertStringContainsString('├── StaticSource', $output);
        $this->assertStringContainsString('└── StaticSource', $output);
    }

    public function test_non_generic_metadata_is_displayed(): void
    {
        $node = new ResolutionTrace('App\\HttpSource');
        $node->addMetadata('outcome', 'ok');
        $node->addMetadata('has_value', true);
        $node->addMetadata('value', 'response');
        $node->addMetadata('duration_ms', 100.0);
        $node->addMetadata('http_response', ['status' => 200, 'body' => 'ok']);

        $output = $this->formatter->format($node);

        $this->assertStringContainsString('http_response:', $output);
        $this->assertStringContainsString('"status":200', $output);
    }

    public function test_error_node_displays_error_message(): void
    {
        $node = new ResolutionTrace('App\\Source');
        $node->addMetadata('outcome', 'err');
        $node->addMetadata('error', 'Something went wrong');
        $node->addMetadata('duration_ms', 1.0);

        $output = $this->formatter->format($node);

        $this->assertStringContainsString('err', $output);
        $this->assertStringContainsString('error: Something went wrong', $output);
    }

    public function test_deeply_nested_tree_indentation(): void
    {
        $root = new ResolutionTrace('Level0');
        $root->addMetadata('outcome', 'ok');
        $root->addMetadata('has_value', true);
        $root->addMetadata('value', 1);
        $root->addMetadata('duration_ms', 10.0);

        $current = $root;
        for ($i = 1; $i <= 5; $i++) {
            $child = new ResolutionTrace("Level{$i}");
            $child->addMetadata('outcome', 'ok');
            $child->addMetadata('has_value', true);
            $child->addMetadata('value', $i);
            $child->addMetadata('duration_ms', 1.0);
            $current->addChild($child);
            $current = $child;
        }

        $output = $this->formatter->format($root);

        // Each level should have increasing indentation
        $lines = explode("\n", $output);
        $this->assertGreaterThanOrEqual(6, count($lines));

        // The root should not be indented
        $this->assertStringStartsWith('Level0', $lines[0]);
    }

    public function test_short_name_extracts_class_basename(): void
    {
        $node = new ResolutionTrace('Superscript\\Axiom\\Sources\\StaticSource');
        $node->addMetadata('outcome', 'ok');
        $node->addMetadata('has_value', true);
        $node->addMetadata('value', 1);
        $node->addMetadata('duration_ms', 0.1);

        $output = $this->formatter->format($node);

        $this->assertStringContainsString('StaticSource', $output);
        $this->assertStringNotContainsString('Superscript\\Axiom\\Sources\\', $output);
    }

    public function test_node_without_value(): void
    {
        $node = new ResolutionTrace('App\\Source');
        $node->addMetadata('outcome', 'ok');
        $node->addMetadata('has_value', false);
        $node->addMetadata('duration_ms', 0.5);

        $output = $this->formatter->format($node);

        $this->assertStringContainsString('ok', $output);
        $this->assertStringNotContainsString('value:', $output);
    }

    public function test_string_metadata_values_not_json_encoded(): void
    {
        $node = new ResolutionTrace('App\\Source');
        $node->addMetadata('outcome', 'ok');
        $node->addMetadata('has_value', true);
        $node->addMetadata('value', 1);
        $node->addMetadata('duration_ms', 0.1);
        $node->addMetadata('custom_string', 'hello world');

        $output = $this->formatter->format($node);

        $this->assertStringContainsString('custom_string: hello world', $output);
        // Should not be json-encoded (no quotes around the string)
        $this->assertStringNotContainsString('"hello world"', $output);
    }

    public function test_mixed_children_branching_symbols(): void
    {
        $root = new ResolutionTrace('Root');
        $root->addMetadata('outcome', 'ok');
        $root->addMetadata('has_value', true);
        $root->addMetadata('value', 1);
        $root->addMetadata('duration_ms', 10.0);

        $child1 = new ResolutionTrace('Child1');
        $child1->addMetadata('outcome', 'ok');
        $child1->addMetadata('has_value', true);
        $child1->addMetadata('value', 1);
        $child1->addMetadata('duration_ms', 1.0);

        $child2 = new ResolutionTrace('Child2');
        $child2->addMetadata('outcome', 'ok');
        $child2->addMetadata('has_value', true);
        $child2->addMetadata('value', 2);
        $child2->addMetadata('duration_ms', 1.0);

        $child3 = new ResolutionTrace('Child3');
        $child3->addMetadata('outcome', 'ok');
        $child3->addMetadata('has_value', true);
        $child3->addMetadata('value', 3);
        $child3->addMetadata('duration_ms', 1.0);

        $root->addChild($child1);
        $root->addChild($child2);
        $root->addChild($child3);

        $output = $this->formatter->format($root);

        // First two children use ├──, last uses └──
        $this->assertStringContainsString('├── Child1', $output);
        $this->assertStringContainsString('├── Child2', $output);
        $this->assertStringContainsString('└── Child3', $output);
    }
}
