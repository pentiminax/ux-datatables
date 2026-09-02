<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
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
     * ColumnControl searchList on integer/float/date/boolean columns used to bind the raw
     * list with IN and no Doctrine type. Selecting a value from the list then 500s on
     * PostgreSQL (`operator does not exist: integer = text`) and, for garbage terms,
     * silently matches 0/false on MySQL.
     *
     * @param list<mixed> $values
     */
    #[Test]
    #[DataProvider('unbindable_typed_lists')]
    public function it_skips_an_unbindable_list_on_a_typed_column(string $field, string $doctrineType, array $values): void
    {
        $qb = $this->queryBuilderWithFieldType($field, $doctrineType);
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $this->applyList($qb, TextColumn::new($field)->setField($field), $values);
    }

    /**
     * @return iterable<string, array{string, string, list<mixed>}>
     */
    public static function unbindable_typed_lists(): iterable
    {
        yield 'decimal on an integer column' => ['age', 'integer', ['1.5']];
        yield 'scientific notation on an integer column' => ['age', 'integer', ['1e2']];
        yield 'non-numeric on an integer column' => ['age', 'integer', ['abc']];
        yield 'non-numeric on a float column' => ['price', 'float', ['abc']];
        yield 'unparsable date' => ['birthDate', 'date', ['not-a-date']];
        yield 'garbage on a boolean column' => ['active', 'boolean', ['yes please']];
    }

    #[Test]
    public function it_binds_an_integer_list_with_the_doctrine_type(): void
    {
        $qb = $this->queryBuilderWithFieldType('age', 'integer');
        $qb->method('expr')->willReturn(new Expr());

        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value, mixed $type = null) use ($qb): QueryBuilder {
                static $call = 0;
                $expected    = [
                    ['age_in_0', '42', 'integer'],
                    ['age_in_1', '7', 'integer'],
                ];
                $this->assertSame($expected[$call], [$name, $value, $type]);
                ++$call;

                return $qb;
            });

        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Orx(['e.age = :age_in_0', 'e.age = :age_in_1']));

        $this->applyList($qb, TextColumn::new('age')->setField('age'), ['  42  ', '7']);
    }

    #[Test]
    public function it_binds_a_float_list_with_the_doctrine_type(): void
    {
        $qb = $this->queryBuilderWithFieldType('price', 'float');
        $qb->method('expr')->willReturn(new Expr());
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('price_in_0', '19.99', 'float');
        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Orx(['e.price = :price_in_0']));

        $this->applyList($qb, TextColumn::new('price')->setField('price'), ['19.99']);
    }

    #[Test]
    public function it_binds_a_date_list_with_a_parsed_value_and_the_doctrine_type(): void
    {
        $qb = $this->queryBuilderWithFieldType('birthDate', 'date');
        $qb->method('expr')->willReturn(new Expr());
        $qb->expects($this->once())
            ->method('setParameter')
            ->with(
                'birthDate_in_0',
                $this->callback(static fn (\DateTimeImmutable $value): bool => '2026-08-19' === $value->format('Y-m-d')),
                'date',
            );
        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Orx(['e.birthDate = :birthDate_in_0']));

        $this->applyList($qb, TextColumn::new('birthDate')->setField('birthDate'), ['2026-08-19']);
    }

    #[Test]
    public function it_binds_a_boolean_list_with_a_parsed_value_and_the_doctrine_type(): void
    {
        $qb = $this->queryBuilderWithFieldType('active', 'boolean');
        $qb->method('expr')->willReturn(new Expr());
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('active_in_0', true, 'boolean');
        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Orx(['e.active = :active_in_0']));

        $this->applyList($qb, TextColumn::new('active')->setField('active'), ['true']);
    }

    #[Test]
    public function it_matches_is_null_for_an_integer_list_of_only_the_empty_value(): void
    {
        $qb = $this->queryBuilderWithFieldType('age', 'integer');
        $qb->method('expr')->willReturn(new Expr());
        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Comparison('e.age', 'IS', 'NULL'));
        $qb->expects($this->never())->method('setParameter');

        $this->applyList($qb, TextColumn::new('age')->setField('age'), ['']);
    }

    #[Test]
    public function it_combines_typed_equalities_with_is_null_when_the_empty_value_is_selected_alongside_integers(): void
    {
        $qb = $this->queryBuilderWithFieldType('age', 'integer');
        $qb->method('expr')->willReturn(new Expr());
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('age_in_0', '42', 'integer');
        $qb->expects($this->once())
            ->method('andWhere')
            ->with(new Expr\Orx(['e.age = :age_in_0', new Expr\Comparison('e.age', 'IS', 'NULL')]));

        $this->applyList($qb, TextColumn::new('age')->setField('age'), ['42', '']);
    }

    #[Test]
    public function it_skips_a_virtual_column_the_root_entity_does_not_map(): void
    {
        $qb = $this->unmappedFieldQueryBuilder('donorProviderName');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $strategy = $this->createMock(SearchStrategyInterface::class);
        $strategy->expects($this->never())->method('apply');

        $filter  = new ColumnControlSearchFilter(new SearchStrategyRegistry([], $strategy));
        $context = $this->singleColumnContext(
            TextColumn::new('donorProviderName', 'Donor'),
            columnControl: new ColumnControl(search: new ColumnControlSearch('acme', ColumnControlLogic::Contains, 'text')),
        );

        $filter->apply($qb, $context);
    }

    #[Test]
    public function it_reaches_the_strategy_for_a_virtual_column_carrying_a_search_field_override(): void
    {
        $qb = $this->joinRecordingQueryBuilder();

        $strategy = $this->createMock(SearchStrategyInterface::class);
        $strategy->expects($this->once())->method('apply');

        $filter = new ColumnControlSearchFilter(new SearchStrategyRegistry([], $strategy));
        $column = TextColumn::new('donorProviderName', 'Donor')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->setSearchField('dp.name');

        $filter->apply($qb, $this->singleColumnContext(
            $column,
            columnControl: new ColumnControl(search: new ColumnControlSearch('acme', ColumnControlLogic::Contains, 'text')),
        ));

        $this->assertSame([['e.donorProvider', 'dp', null, null]], $this->capturedJoins());
    }

    #[Test]
    public function it_applies_an_in_clause_against_the_search_field_override(): void
    {
        $qb = $this->queryBuilderWithFieldType('legalName', 'string');
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.legalName IN (:legalName_in)');
        $qb->expects($this->once())
            ->method('setParameter')
            ->with(':legalName_in', ['acme']);

        $this->applyList(
            $qb,
            TextColumn::new('donorProviderName')->setSearchField('legalName'),
            ['acme'],
        );
    }

    /**
     * @param list<mixed> $values
     */
    private function applyList(QueryBuilder $qb, ColumnInterface $column, array $values): void
    {
        $filter  = new ColumnControlSearchFilter(new SearchStrategyRegistry([], $this->createMock(SearchStrategyInterface::class)));
        $context = $this->singleColumnContext($column, columnControl: new ColumnControl(list: $values));

        $filter->apply($qb, $context);
    }
}
