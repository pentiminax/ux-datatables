<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Filter\ColumnControlSearchFilter;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Tests\Support\BuildsQueryFilterContext;
use Pentiminax\UX\DataTables\Tests\Support\BuildsTypedFieldQueryBuilder;
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
    use BuildsTypedFieldQueryBuilder;

    private const string UUID = '018f2c3e-1234-7abc-9def-0123456789ab';

    private const string ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

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

    #[Test]
    public function it_applies_an_in_clause_for_a_text_column_list(): void
    {
        $qb = $this->queryBuilderWithFieldType('name', 'string');
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.name IN (:name_in)');
        $qb->expects($this->once())
            ->method('setParameter')
            ->with(':name_in', ['acme', 'beta']);

        $this->applyList($qb, TextColumn::new('name')->setField('name'), ['acme', 'beta']);
    }

    #[Test]
    public function it_matches_is_null_for_a_text_column_list_of_only_the_empty_value(): void
    {
        $qb = $this->queryBuilderWithFieldType('name', 'string');
        $qb->method('expr')->willReturn(new Expr());
        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Comparison('e.name', 'IS', 'NULL'));
        $qb->expects($this->never())->method('setParameter');

        $this->applyList($qb, TextColumn::new('name')->setField('name'), ['']);
    }

    #[Test]
    public function it_combines_an_in_clause_with_is_null_when_the_empty_value_is_selected_alongside_others(): void
    {
        $qb = $this->queryBuilderWithFieldType('name', 'string');
        $qb->method('expr')->willReturn(new Expr());
        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Orx(['e.name IN (:name_in)', new Expr\Comparison('e.name', 'IS', 'NULL')]));
        $qb->expects($this->once())
            ->method('setParameter')
            ->with(':name_in', ['acme']);

        $this->applyList($qb, TextColumn::new('name')->setField('name'), ['acme', '']);
    }

    /**
     * Column Control searchList on a native UUID/ULID column used to bind the raw list
     * with IN. An empty option or a partial identifier then 500s (SQLSTATE 22P02 /
     * Doctrine conversion), and a valid ULID bound as a string never matches.
     *
     * @param list<mixed> $values
     */
    #[Test]
    #[DataProvider('unbindable_uuid_lists')]
    public function it_skips_an_unbindable_list_on_a_uuid_column(array $values): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'guid');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $this->applyList($qb, TextColumn::new('id')->setField('id'), $values);
    }

    /**
     * @return iterable<string, array{list<mixed>}>
     */
    public static function unbindable_uuid_lists(): iterable
    {
        yield 'empty string' => [['']];
        yield 'partial identifier' => [['018f2c3e']];
        yield 'unhyphenated uuid' => [['018f2c3e12347abc9def0123456789ab']];
        yield 'only junk' => [['not-a-uuid', '']];
        yield 'ulid on a guid column' => [[self::ULID]];
    }

    #[Test]
    public function it_skips_a_uuid_list_on_an_ulid_column(): void
    {
        $qb = $this->queryBuilderWithFieldType('id', 'ulid');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $this->applyList($qb, TextColumn::new('id')->setField('id'), [self::UUID]);
    }

    #[Test]
    #[DataProvider('bindable_uuid_lists')]
    public function it_binds_valid_uuid_list_values_with_the_doctrine_type(
        string $doctrineType,
        array $values,
        array $expectedParameters,
        array $expectedOrArguments,
    ): void {
        $qb = $this->queryBuilderWithFieldType('id', $doctrineType);
        $qb->method('expr')->willReturn(new Expr());

        $setParameter = $qb->expects($this->exactly(\count($expectedParameters)))
            ->method('setParameter');
        $setParameter->willReturnCallback(function (string $name, mixed $value, mixed $type = null) use ($expectedParameters, $qb): QueryBuilder {
            static $call = 0;
            $this->assertSame($expectedParameters[$call], [$name, $value, $type]);
            ++$call;

            return $qb;
        });

        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Orx($expectedOrArguments));

        $this->applyList($qb, TextColumn::new('id')->setField('id'), $values);
    }

    /**
     * @return iterable<string, array{string, list<mixed>, list<array{string, string, string}>, list<string>}>
     */
    public static function bindable_uuid_lists(): iterable
    {
        yield 'guid list with padding and junk' => [
            'guid',
            ['  '.self::UUID.'  ', '', '018f2c3e'],
            [['id_in_0', self::UUID, 'guid']],
            ['e.id = :id_in_0'],
        ];

        yield 'guid list drops a ulid' => [
            'guid',
            [self::UUID, self::ULID],
            [['id_in_0', self::UUID, 'guid']],
            ['e.id = :id_in_0'],
        ];

        yield 'ulid list' => [
            'ulid',
            [self::ULID],
            [['id_in_0', self::ULID, 'ulid']],
            ['e.id = :id_in_0'],
        ];

        yield 'ulid list drops a uuid' => [
            'ulid',
            [self::UUID, self::ULID],
            [['id_in_1', self::ULID, 'ulid']],
            ['e.id = :id_in_1'],
        ];

        yield 'binary uuid list of two values' => [
            'uuid_binary',
            [self::UUID, 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'],
            [
                ['id_in_0', self::UUID, 'uuid_binary'],
                ['id_in_1', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'uuid_binary'],
            ],
            ['e.id = :id_in_0', 'e.id = :id_in_1'],
        ];
    }

    /**
     * @param list<mixed> $values
     */
    private function applyList(QueryBuilder $qb, TextColumn $column, array $values): void
    {
        $filter  = new ColumnControlSearchFilter(new SearchStrategyRegistry([], $this->createMock(SearchStrategyInterface::class)));
        $context = $this->singleColumnContext($column, columnControl: new ColumnControl(list: $values));

        $filter->apply($qb, $context);
    }
}
