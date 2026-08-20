<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Strategy\ComparisonSearchStrategy;
use Pentiminax\UX\DataTables\Tests\Support\BuildsTypedFieldQueryBuilder;
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

    /**
     * Two independent guards, both observable as "the query builder is never touched":
     * a LIKE against a uuid-typed column crashes on PostgreSQL and SQL Server, and a
     * malformed identifier bound with an identifier Doctrine type makes conversion throw
     * at execution time.
     */
    #[Test]
    #[DataProvider('uuid_skip_cases')]
    public function it_skips_an_unbindable_predicate_on_a_uuid_column(ColumnControlLogic $logic): void
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

    #[Test]
    public function it_skips_a_ulid_on_a_guid_column(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'guid');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $strategy = new ComparisonSearchStrategy(ColumnControlLogic::Equal);
        $column   = TextColumn::new('id')->setField('id');
        $search   = new ColumnControlSearch('01ARZ3NDEKTSV4RRFFQ69G5FAV', ColumnControlLogic::Equal, 'text');

        $strategy->apply($qb, $column, $search, 3, 'e');
    }

    #[Test]
    public function it_skips_a_uuid_on_an_ulid_column(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'ulid');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $strategy = new ComparisonSearchStrategy(ColumnControlLogic::Equal);
        $column   = TextColumn::new('id')->setField('id');
        $search   = new ColumnControlSearch('018f2c3e-1234-7abc-9def-0123456789ab', ColumnControlLogic::Equal, 'text');

        $strategy->apply($qb, $column, $search, 3, 'e');
    }

    #[Test]
    #[DataProvider('date_skip_cases')]
    public function it_skips_an_unbindable_predicate_on_a_date_column(ColumnControlLogic $logic, string $value): void
    {
        $qb = $this->queryBuilderWithFieldType('birthDate', 'date');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $strategy = new ComparisonSearchStrategy($logic);
        $column   = TextColumn::new('birthDate')->setField('birthDate');
        $search   = new ColumnControlSearch($value, $logic, 'text');

        $strategy->apply($qb, $column, $search, 3, 'e');
    }

    #[Test]
    public function it_applies_equality_on_a_date_column_with_the_doctrine_type_and_a_parsed_value(): void
    {
        $qb = $this->queryBuilderWithFieldType('birthDate', 'date');

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.birthDate = :column_control_param_3');

        $qb->expects($this->once())
            ->method('setParameter')
            ->with(
                'column_control_param_3',
                $this->callback(static fn (\DateTimeImmutable $value): bool => '2026-08-19' === $value->format('Y-m-d')),
                'date',
            );

        $strategy = new ComparisonSearchStrategy(ColumnControlLogic::Equal);
        $column   = TextColumn::new('birthDate')->setField('birthDate');
        $search   = new ColumnControlSearch('2026-08-19', ColumnControlLogic::Equal, 'text');

        $strategy->apply($qb, $column, $search, 3, 'e');
    }

    /**
     * @return iterable<string, array{ColumnControlLogic, string}>
     */
    public static function date_skip_cases(): iterable
    {
        yield 'starts (LIKE on a date column)' => [ColumnControlLogic::Starts, '2026'];
        yield 'ends (LIKE on a date column)' => [ColumnControlLogic::Ends, '2026'];
        yield 'notContains (LIKE on a date column)' => [ColumnControlLogic::NotContains, '2026'];
        yield 'equal (unparsable value)' => [ColumnControlLogic::Equal, 'not-a-date'];
        yield 'greater (unparsable value)' => [ColumnControlLogic::Greater, 'not-a-date'];
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
            "e.name LIKE :column_control_param_3 ESCAPE '\\'",
            'Ali%',
        ];

        yield 'ends' => [
            ColumnControlLogic::Ends,
            'ice',
            "e.name LIKE :column_control_param_3 ESCAPE '\\'",
            '%ice',
        ];

        yield 'notContains' => [
            ColumnControlLogic::NotContains,
            'ali',
            "e.name NOT LIKE :column_control_param_3 ESCAPE '\\'",
            '%ali%',
        ];

        yield 'starts with like wildcards is escaped, not interpreted' => [
            ColumnControlLogic::Starts,
            '50%_off',
            "e.name LIKE :column_control_param_3 ESCAPE '\\'",
            '50\%\_off%',
        ];
    }

    /**
     * @return iterable<string, array{ColumnControlLogic}>
     */
    public static function uuid_skip_cases(): iterable
    {
        yield 'starts (LIKE on a uuid column)' => [ColumnControlLogic::Starts];
        yield 'ends (LIKE on a uuid column)' => [ColumnControlLogic::Ends];
        yield 'notContains (LIKE on a uuid column)' => [ColumnControlLogic::NotContains];
        yield 'equal (malformed identifier)' => [ColumnControlLogic::Equal];
        yield 'notEqual (malformed identifier)' => [ColumnControlLogic::NotEqual];
        yield 'greater (malformed identifier)' => [ColumnControlLogic::Greater];
    }
}
