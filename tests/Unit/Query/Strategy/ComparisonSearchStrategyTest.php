<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Strategy\ComparisonSearchStrategy;
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
        $search   = new ColumnControlSearch($value, $logic->value, 'text');
        $qb       = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($expectedExpression);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('column_control_param_3', $expectedParameter);

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
}
