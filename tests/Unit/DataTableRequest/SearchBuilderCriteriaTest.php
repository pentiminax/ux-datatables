<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\SearchBuilderCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SearchBuilderCriteria::class)]
final class SearchBuilderCriteriaTest extends TestCase
{
    #[Test]
    public function it_normalizes_nested_groups(): void
    {
        $tree = SearchBuilderCriteria::parse([
            'logic'    => 'OR',
            'criteria' => [
                ['origData' => 'age', 'condition' => '>', 'type' => 'num', 'value' => ['50']],
                ['logic' => 'AND', 'criteria' => [
                    ['origData' => 'name', 'condition' => 'between', 'value' => ['a', 'z']],
                    ['origData' => 'city', 'condition' => 'null'],
                ]],
            ],
        ]);

        $this->assertSame('OR', $tree['logic']);
        $this->assertSame(['column' => 'age', 'condition' => '>', 'type' => 'num', 'values' => ['50']], $tree['criteria'][0]);
        $this->assertSame('AND', $tree['criteria'][1]['logic']);
        $this->assertCount(2, $tree['criteria'][1]['criteria']);
    }

    #[Test]
    public function it_drops_incomplete_criteria(): void
    {
        $tree = SearchBuilderCriteria::parse(['criteria' => [
            ['origData' => 'age', 'condition' => '=', 'value' => ['']],
            ['origData' => 'age', 'condition' => 'between', 'value' => ['1']],
            ['origData' => 'name', 'condition' => 'contains', 'value' => ['x']],
        ]]);

        $this->assertCount(1, $tree['criteria']);
    }

    #[Test]
    public function it_skips_the_blank_criterion_datatables_sends_for_a_new_row(): void
    {
        $tree = SearchBuilderCriteria::parse([
            'logic'    => 'AND',
            'criteria' => [
                ['condition' => '>', 'data' => 'Id', 'origData' => 'id', 'type' => 'num', 'value' => ['2']],
                ['condition' => 'undefined', 'data' => 'undefined', 'origData' => 'undefined', 'type' => '', 'value' => []],
            ],
        ]);

        $this->assertCount(1, $tree['criteria']);
        $this->assertSame('id', $tree['criteria'][0]['column']);
    }

    #[Test]
    public function it_reads_the_parameter_from_the_request(): void
    {
        $request = Request::create('/ajax', 'GET', [
            'draw'          => '1',
            'searchBuilder' => [
                'logic'    => 'AND',
                'criteria' => [
                    ['condition' => '>', 'origData' => 'id', 'type' => 'num', 'value' => ['2'], 'value1' => '2'],
                    ['condition' => 'undefined', 'data' => 'undefined', 'origData' => 'undefined', 'type' => ''],
                ],
            ],
        ]);

        $parsed = DataTableRequest::fromRequest($request)->searchBuilder;

        $this->assertCount(1, $parsed['criteria']);
        $this->assertNull(DataTableRequest::fromRequest(Request::create('/ajax', 'GET', ['searchBuilder' => 'x']))->searchBuilder);
        $this->assertNull(DataTableRequest::fromRequest(Request::create('/ajax'))->searchBuilder);
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function it_rejects_the_whole_payload_when_invalid(mixed $payload): void
    {
        $this->assertNull(SearchBuilderCriteria::parse($payload));
    }

    public static function invalidPayloads(): iterable
    {
        yield 'not an array' => ['x'];
        yield 'empty' => [[]];
        yield 'unknown logic' => [['logic' => 'XOR', 'criteria' => [['origData' => 'a', 'condition' => '=', 'value' => ['1']]]]];
        yield 'unknown condition' => [['criteria' => [['origData' => 'a', 'condition' => 'regex', 'value' => ['1']]]]];
        yield 'non scalar value' => [['criteria' => [['origData' => 'a', 'condition' => '=', 'value' => [['1']]]]]];
        yield 'too many values' => [['criteria' => [['origData' => 'a', 'condition' => '=', 'value' => ['1', '2', '3']]]]];
        yield 'missing column' => [['criteria' => [['condition' => '=', 'value' => ['1']]]]];
        yield 'oversized value' => [['criteria' => [['origData' => 'a', 'condition' => '=', 'value' => [str_repeat('a', 256)]]]]];
        yield 'too deep' => [self::nest(SearchBuilderCriteria::MAX_DEPTH + 1)];
        yield 'too many nodes' => [['criteria' => array_fill(0, SearchBuilderCriteria::MAX_NODES + 1, ['origData' => 'a', 'condition' => '=', 'value' => ['1']])]];
    }

    private static function nest(int $depth): array
    {
        $node = ['criteria' => [['origData' => 'a', 'condition' => '=', 'value' => ['1']]]];
        for ($i = 1; $i < $depth; ++$i) {
            $node = ['criteria' => [$node]];
        }

        return $node;
    }
}
