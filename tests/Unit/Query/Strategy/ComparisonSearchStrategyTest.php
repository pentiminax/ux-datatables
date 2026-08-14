<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Strategy\ComparisonSearchStrategy;
use Pentiminax\UX\DataTables\Tests\Unit\Query\BuildsTypedFieldQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ComparisonSearchStrategy::class)]
final class ComparisonSearchStrategyTest extends TestCase
{
    use BuildsTypedFieldQueryBuilder;

    #[Test]
    #[DataProvider('comparison_cases')]
    public function it_applies_expected_comparison_expression(
        ColumnControlLogic $logic,
        string $value,
        string $expectedExpression,
        string $expectedParameter,
    ): void {
        $strategy = new ComparisonSearchStrategy($logic);
        $column   = TextColumn::new('name');

        $search = new ColumnControlSearch(
            value: $value,
            logic: $logic,
            type: 'text'
        );

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($expectedExpression);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('column_control_param_3', $expectedParameter, null);

        $strategy->apply($qb, $column, $search, 3, 'e');
    }

    #[Test]
    #[DataProvider('like_logic_cases')]
    public function it_skips_like_logic_on_a_uuid_column(ColumnControlLogic $logic): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'guid');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $strategy = new ComparisonSearchStrategy($logic);
        $column   = TextColumn::new('id')->setField('id');
        $search   = new ColumnControlSearch('018f2c3e', $logic, 'text');

        $strategy->apply($qb, $column, $search, 3, 'e');
    }

    #[Test]
    public function it_applies_equality_on_a_uuid_column_with_the_doctrine_type(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'guid');

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.id = :column_control_param_3');

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('column_control_param_3', '018f2c3e-1234-7abc-9def-0123456789ab', 'guid');

        $strategy = new ComparisonSearchStrategy(ColumnControlLogic::Equal);
        $column   = TextColumn::new('id')->setField('id');
        $search   = new ColumnControlSearch('  018f2c3e-1234-7abc-9def-0123456789ab  ', ColumnControlLogic::Equal, 'text');

        $strategy->apply($qb, $column, $search, 3, 'e');
    }

    /**
     * A malformed identifier bound with an identifier Doctrine type makes conversion
     * throw at execution time, so it must be skipped like any other non-matching term.
     */
    #[Test]
    #[DataProvider('comparison_logic_cases')]
    public function it_skips_a_malformed_identifier_on_a_uuid_column(ColumnControlLogic $logic): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'guid');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $strategy = new ComparisonSearchStrategy($logic);
        $column   = TextColumn::new('id')->setField('id');
        $search   = new ColumnControlSearch('018f2c3e', $logic, 'text');

        $strategy->apply($qb, $column, $search, 3, 'e');
    }

    #[Test]
    public function it_returns_logic_value(): void
    {
        $strategy = new ComparisonSearchStrategy(ColumnControlLogic::Ends);

        $this->assertSame('ends', $strategy->getLogic());
    }

    #[Test]
    public function it_rejects_non_comparison_logic(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Logic "empty" is not compatible');

        new ComparisonSearchStrategy(ColumnControlLogic::Empty);
    }

    /**
     * @return iterable<string, array{ColumnControlLogic, string, string, string}>
     */
    public static function comparison_cases(): iterable
    {
        yield 'equal' => [
            ColumnControlLogic::Equal,
            'Alice',
            'e.name = :column_control_param_3',
            'Alice',
        ];

        yield 'starts' => [
            ColumnControlLogic::Starts,
            'Ali',
            'e.name LIKE :column_control_param_3',
            'Ali%',
        ];
    }

    /**
     * @return iterable<string, array{ColumnControlLogic}>
     */
    public static function like_logic_cases(): iterable
    {
        yield 'starts' => [ColumnControlLogic::Starts];
        yield 'ends' => [ColumnControlLogic::Ends];
        yield 'notContains' => [ColumnControlLogic::NotContains];
    }

    /**
     * @return iterable<string, array{ColumnControlLogic}>
     */
    public static function comparison_logic_cases(): iterable
    {
        yield 'equal' => [ColumnControlLogic::Equal];
        yield 'notEqual' => [ColumnControlLogic::NotEqual];
        yield 'greater' => [ColumnControlLogic::Greater];
    }
}
