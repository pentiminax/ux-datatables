<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ColumnControl::class)]
#[CoversClass(ColumnControlSearch::class)]
final class ColumnControlTest extends TestCase
{
    /**
     * Each of these payloads used to abort the Ajax request with a 500 (ValueError on the
     * logic enum, TypeError on a non-array search or list, warning plus TypeError on a
     * missing logic). Transport-level parsing drops them instead, which is the contract
     * the query intent factory documents.
     *
     * @param array<string, mixed> $columnControl
     */
    #[Test]
    #[DataProvider('provideMalformedColumnControls')]
    public function it_drops_a_malformed_column_control(array $columnControl): void
    {
        $control = self::parse($columnControl)->columns->getColumnByName('department')?->columnControl;

        $this->assertInstanceOf(ColumnControl::class, $control);
        $this->assertNull($control->search);
        $this->assertSame([], $control->list);
    }

    public static function provideMalformedColumnControls(): iterable
    {
        yield 'unknown search logic' => [['search' => ['logic' => 'bogus', 'value' => 'x', 'type' => 'text']]];
        yield 'non-array search' => [['search' => 'oops']];
        yield 'non-array list' => [['list' => 'oops']];
        yield 'search without logic' => [['search' => ['value' => 'x']]];
        yield 'search without value' => [['search' => ['logic' => 'equal', 'type' => 'text']]];
        yield 'search with array value' => [['search' => ['logic' => 'equal', 'value' => ['x'], 'type' => 'text']]];
        yield 'search without type' => [['search' => ['logic' => 'equal', 'value' => 'x']]];
        yield 'search with non-string type' => [['search' => ['logic' => 'equal', 'value' => 'x', 'type' => ['text']]]];
    }

    #[Test]
    public function it_parses_a_well_formed_column_control(): void
    {
        $search = self::parse(['search' => ['logic' => 'equal', 'value' => 'active', 'type' => 'text']])
            ->columns->getColumnByName('department')?->columnControl?->search;

        $this->assertInstanceOf(ColumnControlSearch::class, $search);
        $this->assertSame('active', $search->value);
        $this->assertSame(ColumnControlLogic::Equal, $search->logic);
        $this->assertSame('text', $search->type);
    }

    #[Test]
    public function it_drops_non_scalar_list_entries(): void
    {
        $control = self::parse(['list' => ['Sales', ['nested' => 'value'], '']])
            ->columns->getColumnByName('department')?->columnControl;

        $this->assertSame(['Sales', ''], $control?->list);
    }

    /**
     * @param array<string, mixed> $columnControl
     */
    private static function parse(array $columnControl): DataTableRequest
    {
        return DataTableRequest::fromRequest(Request::create('/datatables', 'GET', [
            'draw'    => '1',
            'columns' => [
                [
                    'data'          => '0',
                    'name'          => 'department',
                    'searchable'    => 'true',
                    'orderable'     => 'true',
                    'columnControl' => $columnControl,
                ],
            ],
        ]));
    }
}
