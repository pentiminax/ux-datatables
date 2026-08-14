<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Strategy;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
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
    #[DataProvider('expression_cases')]
    public function it_applies_expected_expression(
        ColumnInterface $column,
        string $searchType,
        bool $negated,
        string $expectedExpression,
    ): void {
        $strategy = new NullnessSearchStrategy($negated);

        $search = new ColumnControlSearch(
            value: '',
            logic: ColumnControlLogic::from($strategy->getLogic()),
            type: $searchType
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
     * @return iterable<string, array{ColumnInterface, string, bool, string}>
     */
    public static function expression_cases(): iterable
    {
        yield 'text empty' => [TextColumn::new('name'), 'text', false, "e.name IS NULL OR e.name = ''"];
        yield 'text not empty' => [TextColumn::new('name'), 'text', true, "e.name IS NOT NULL AND e.name <> ''"];
        yield 'numeric empty' => [NumberColumn::new('price'), 'num', false, 'e.price IS NULL'];
        yield 'numeric not empty' => [NumberColumn::new('price'), 'num', true, 'e.price IS NOT NULL'];
        yield 'date empty' => [DateColumn::new('sentAt'), 'date', false, 'e.sentAt IS NULL'];
        yield 'date not empty' => [DateColumn::new('sentAt'), 'date', true, 'e.sentAt IS NOT NULL'];
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
