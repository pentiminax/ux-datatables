<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Strategy;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Strategy\NullnessSearchStrategy;
use Pentiminax\UX\DataTables\Tests\Support\BuildsTypedFieldQueryBuilder;
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
    use BuildsTypedFieldQueryBuilder;

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

    /**
     * Column Control "Empty" / "Not empty" on a TextColumn mapped to a native UUID
     * must not emit `= ''`. PostgreSQL rejects that comparison with SQLSTATE 22P02.
     */
    #[Test]
    #[DataProvider('uuid_column_cases')]
    public function it_applies_null_only_expression_on_a_uuid_column(bool $negated, string $expectedExpression): void
    {
        $strategy = new NullnessSearchStrategy($negated);
        $column   = TextColumn::new('id')->setField('id');

        $search = new ColumnControlSearch(
            value: '',
            logic: ColumnControlLogic::from($strategy->getLogic()),
            type: 'text'
        );

        $qb = $this->queryBuilderWithFieldType('id', 'uuid');
        $qb->method('expr')->willReturn(new Expr());
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
    public static function uuid_column_cases(): iterable
    {
        yield 'empty' => [false, 'e.id IS NULL'];
        yield 'not empty' => [true, 'e.id IS NOT NULL'];
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function logic_cases(): iterable
    {
        yield 'empty' => [false, 'empty'];
        yield 'not empty' => [true, 'notEmpty'];
    }

    // -----------------------------------------------------------------------
    // setSearchField override
    // -----------------------------------------------------------------------

    #[Test]
    public function it_uses_search_field_override_for_nullness_check(): void
    {
        $strategy = new NullnessSearchStrategy(false);

        $expr = $this->createMock(Expr::class);
        $expr->method('isNull')
            ->with('donorProvider.amount')
            ->willReturn('donorProvider.amount IS NULL');

        // Track joins added dynamically so RelationFieldResolver sees them.
        $addedJoin = $this->createMock(Join::class);
        $addedJoin->method('getAlias')->willReturn('donorProvider');
        $addedJoin->method('getJoin')->willReturn('e.donorProvider');

        $joinParts = [];
        $qb        = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturnCallback(function () use (&$joinParts) {
            return $joinParts;
        });
        $qb->method('expr')->willReturn($expr);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.donorProvider', 'donorProvider')
            ->willReturnCallback(function () use ($qb, $addedJoin, &$joinParts) {
                $joinParts['e'][] = $addedJoin;

                return $qb;
            });

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('donorProvider.amount IS NULL');

        // NumberColumn triggers the null-only IS NULL path (no orX wrapping).
        $column = NumberColumn::new('donorProviderAmount')->setSearchField('donorProvider.amount');
        $search = new ColumnControlSearch('', ColumnControlLogic::Empty, 'text');

        $strategy->apply($qb, $column, $search, 0, 'e');
    }
}
