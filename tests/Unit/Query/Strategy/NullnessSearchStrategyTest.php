<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Strategy;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Strategy\NullnessSearchStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NullnessSearchStrategy::class)]
final class NullnessSearchStrategyTest extends TestCase
{
    #[Test]
    #[DataProvider('text_column_cases')]
    public function it_applies_expected_text_expression(bool $negated, string $expectedExpression): void
    {
        $strategy = new NullnessSearchStrategy($negated);
        $column   = TextColumn::new('name');

        $search = new ColumnControlSearch(
            value: '',
            logic: ColumnControlLogic::from($strategy->getLogic()),
            type: 'text'
        );

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('expr')
            ->willReturn(new Expr());

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($expectedExpression);

        $strategy->apply($qb, $column, $search, 0, 'e');
    }

    #[Test]
    #[DataProvider('numeric_column_cases')]
    public function it_applies_expected_numeric_expression(bool $negated, string $expectedExpression): void
    {
        $strategy = new NullnessSearchStrategy($negated);
        $column   = NumberColumn::new('price');

        $search = new ColumnControlSearch(
            value: '',
            logic: ColumnControlLogic::from($strategy->getLogic()),
            type: 'num'
        );

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('expr')
            ->willReturn(new Expr());

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($expectedExpression);

        $strategy->apply($qb, $column, $search, 0, 'e');
    }

    #[Test]
    #[DataProvider('date_column_cases')]
    public function it_applies_expected_date_expression(bool $negated, string $expectedExpression): void
    {
        $strategy = new NullnessSearchStrategy($negated);
        $column   = DateColumn::new('sentAt');

        $search = new ColumnControlSearch(
            value: '',
            logic: ColumnControlLogic::from($strategy->getLogic()),
            type: 'date'
        );

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('expr')
            ->willReturn(new Expr());

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($expectedExpression);

        $strategy->apply($qb, $column, $search, 0, 'e');
    }

    #[Test]
    #[DataProvider('logic_cases')]
    public function it_returns_expected_logic(bool $negated, string $expectedLogic): void
    {
        $strategy = new NullnessSearchStrategy($negated);

        $this->assertSame($expectedLogic, $strategy->getLogic());
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function text_column_cases(): iterable
    {
        yield 'empty' => [false, "e.name IS NULL OR e.name = ''"];
        yield 'not empty' => [true, "e.name IS NOT NULL AND e.name <> ''"];
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function numeric_column_cases(): iterable
    {
        yield 'empty' => [false, 'e.price IS NULL'];
        yield 'not empty' => [true, 'e.price IS NOT NULL'];
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function date_column_cases(): iterable
    {
        yield 'empty' => [false, 'e.sentAt IS NULL'];
        yield 'not empty' => [true, 'e.sentAt IS NOT NULL'];
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function logic_cases(): iterable
    {
        yield 'empty' => [false, 'empty'];
        yield 'not empty' => [true, 'notEmpty'];
    }
}
