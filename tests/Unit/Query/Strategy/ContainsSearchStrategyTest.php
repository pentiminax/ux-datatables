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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * The value is always bound through setParameter, never interpolated into the DQL.
     *
     * @param list<mixed> $expectedParameter the expected setParameter() arguments
     */
    #[Test]
    #[DataProvider('applied_cases')]
    public function it_applies_expected_condition(
        ColumnInterface $column,
        string $searchType,
        int $paramIndex,
        string $value,
        string $expectedExpression,
        array $expectedParameter,
    ): void {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->expects($this->once())->method('setParameter')->with(...$expectedParameter);
        $qb->expects($this->once())->method('andWhere')->with($expectedExpression);

        $search = new ColumnControlSearch($value, ColumnControlLogic::Contains, $searchType);

        (new ContainsSearchStrategy())->apply($qb, $column, $search, $paramIndex, 'e');
    }

    #[Test]
    #[DataProvider('ignored_cases')]
    public function it_leaves_the_query_builder_untouched(?string $field, string $value, string $searchType): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('andWhere');

        $column = null !== $field ? TextColumn::new($field)->setField($field) : $this->columnWithoutField();
        $search = new ColumnControlSearch($value, ColumnControlLogic::Contains, $searchType);

        (new ContainsSearchStrategy())->apply($qb, $column, $search, 0, 'e');
    }

    /**
     * @return iterable<string, array{ColumnInterface, string, int, string, string, list<mixed>}>
     */
    public static function applied_cases(): iterable
    {
        yield 'text column uses LIKE' => [
            TextColumn::new('name')->setField('name'),
            'text',
            3,
            'foo',
            "e.name LIKE :column_control_param_3 ESCAPE '!'",
            ['column_control_param_3', '%foo%'],
        ];

        yield 'text column with like wildcards is escaped, not interpreted' => [
            TextColumn::new('name')->setField('name'),
            'text',
            3,
            '50%_off',
            "e.name LIKE :column_control_param_3 ESCAPE '!'",
            ['column_control_param_3', '%50!%!_off%'],
        ];

        yield 'numeric column uses exact match' => [
            NumberColumn::new('id')->setField('id'),
            'text',
            1,
            '42',
            'e.id = :column_control_param_1',
            ['column_control_param_1', '42', null],
        ];

        // Regression guard: a numeric search type hint must force numeric (exact) handling
        // even when the column itself is not numeric.
        yield 'number search type forces exact match' => [
            TextColumn::new('score')->setField('score'),
            'number',
            2,
            '7',
            'e.score = :column_control_param_2',
            ['column_control_param_2', '7', null],
        ];
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function ignored_cases(): iterable
    {
        yield 'blank value' => ['name', '   ', 'text'];
        yield 'non-numeric value for a numeric search type' => ['score', 'abc', 'numeric'];

        // The upfront null-field guard prevents a null field from reaching the predicate
        // builder (whose $field parameter is a non-null string), which would otherwise
        // TypeError. This applies to every column type, including forced-numeric.
        yield 'null column field' => [null, '7', 'number'];
    }

    private function columnWithoutField(): ColumnInterface
    {
        $column = $this->createStub(ColumnInterface::class);
        $column->method('getField')->willReturn(null);

        return $column;
    }
}
