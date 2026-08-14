<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Filter\ColumnControlSearchFilter;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColumnControlSearchFilter::class)]
final class ColumnControlSearchFilterTest extends TestCase
{
    use BuildsQueryFilterContext;

    /**
     * @return iterable<string, array{ColumnControl}>
     */
    public static function unsupportedColumnControls(): iterable
    {
        yield 'strategy search' => [
            new ColumnControl(search: new ColumnControlSearch('acme', ColumnControlLogic::Contains, 'text')),
        ];

        yield 'list search' => [new ColumnControl(list: ['acme'])];
    }

    #[Test]
    #[DataProvider('unsupportedColumnControls')]
    public function it_skips_column_control_when_field_requires_an_explicit_scalar_path(ColumnControl $columnControl): void
    {
        $strategy = $this->createMock(SearchStrategyInterface::class);
        $strategy->expects($this->never())->method('apply');

        $qb = $this->associationFieldQueryBuilder('client');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('leftJoin');

        $filter  = new ColumnControlSearchFilter(new SearchStrategyRegistry([], $strategy));
        $context = $this->singleColumnContext(TextColumn::new('client', 'Client'), columnControl: $columnControl);

        $filter->apply($qb, $context);
    }

    #[Test]
    public function it_delegates_search_strategy_for_supported_field(): void
    {
        $search = new ColumnControlSearch('acme', ColumnControlLogic::Contains, 'text');
        $qb     = $this->createMock(QueryBuilder::class);

        $strategy = $this->createMock(SearchStrategyInterface::class);
        $strategy->expects($this->once())
            ->method('apply')
            ->with($this->identicalTo($qb), $this->isInstanceOf(TextColumn::class), $this->equalTo($search), 0, 'e');

        $filter  = new ColumnControlSearchFilter(new SearchStrategyRegistry([], $strategy));
        $column  = TextColumn::new('clientName', 'Client')->setField('client.name');
        $context = $this->singleColumnContext($column, columnControl: new ColumnControl(search: $search));

        $filter->apply($qb, $context);
    }
}
