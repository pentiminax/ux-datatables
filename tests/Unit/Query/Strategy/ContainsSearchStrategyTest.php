<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Strategy\ContainsSearchStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ContainsSearchStrategy::class)]
final class ContainsSearchStrategyTest extends TestCase
{
    #[Test]
    public function it_returns_logic_value(): void
    {
        $this->assertSame('contains', (new ContainsSearchStrategy())->getLogic());
    }

    #[Test]
    public function it_applies_like_condition_for_text_column(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with('column_control_param_3', '%foo%');
        $qb->expects($this->once())->method('andWhere')->with('e.name LIKE :column_control_param_3');

        $column = TextColumn::new('name')->setField('name');
        $search = new ColumnControlSearch('foo', ColumnControlLogic::Contains, 'text');

        (new ContainsSearchStrategy())->apply($qb, $column, $search, 3, 'e');
    }

    #[Test]
    public function it_applies_exact_condition_for_numeric_column(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with('column_control_param_1', '42');
        $qb->expects($this->once())->method('andWhere')->with('e.id = :column_control_param_1');

        $column = NumberColumn::new('id')->setField('id');
        $search = new ColumnControlSearch('42', ColumnControlLogic::Contains, 'text');

        (new ContainsSearchStrategy())->apply($qb, $column, $search, 1, 'e');
    }

    /**
     * Regression guard: a numeric search type hint must force numeric (exact) handling
     * even when the column itself is not numeric.
     */
    #[Test]
    public function it_applies_exact_condition_when_search_type_is_number(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with('column_control_param_2', '7');
        $qb->expects($this->once())->method('andWhere')->with('e.score = :column_control_param_2');

        $column = TextColumn::new('score')->setField('score');
        $search = new ColumnControlSearch('7', ColumnControlLogic::Contains, 'number');

        (new ContainsSearchStrategy())->apply($qb, $column, $search, 2, 'e');
    }

    #[Test]
    public function it_does_nothing_when_search_type_is_number_but_value_is_non_numeric(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('andWhere');

        $column = TextColumn::new('score')->setField('score');
        $search = new ColumnControlSearch('abc', ColumnControlLogic::Contains, 'numeric');

        (new ContainsSearchStrategy())->apply($qb, $column, $search, 0, 'e');
    }

    /**
     * The upfront null-field guard prevents a null field from reaching the predicate
     * builder (whose $field parameter is a non-null string), which would otherwise
     * TypeError. This applies to every column type, including forced-numeric.
     */
    #[Test]
    public function it_does_nothing_when_the_column_field_is_null(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('andWhere');

        $column = $this->createMock(ColumnInterface::class);
        $column->method('getField')->willReturn(null);

        $search = new ColumnControlSearch('7', ColumnControlLogic::Contains, 'number');

        (new ContainsSearchStrategy())->apply($qb, $column, $search, 0, 'e');
    }

    #[Test]
    public function it_does_nothing_for_blank_value(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('andWhere');

        $column = TextColumn::new('name')->setField('name');
        $search = new ColumnControlSearch('   ', ColumnControlLogic::Contains, 'text');

        (new ContainsSearchStrategy())->apply($qb, $column, $search, 0, 'e');
    }
}
