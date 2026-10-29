<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\SearchBuilderCriteria;
use Pentiminax\UX\DataTables\Query\Filter\SearchBuilderFilter;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountEvent;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use Pentiminax\UX\DataTables\Tests\Support\BuildsQueryFilterContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the composed predicate against sqlite so the generated DQL is proven valid.
 *
 * @internal
 */
#[CoversClass(SearchBuilderFilter::class)]
final class SearchBuilderFilterTest extends TestCase
{
    use BuildsEntityManager;
    use BuildsQueryFilterContext;

    private const array ALL = ['Alice', 'Bob', 'Alina', 'Carl'];

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountEvent::class, CountTag::class);

        foreach ([[1, 'Alice'], [2, 'Bob'], [3, 'Alina'], [4, 'Carl']] as [$id, $name]) {
            $this->em->persist(new CountCustomer($id, $name));
        }
        foreach ([
            [1, 'launch', '2026-03-10 08:00:00', true, 'x'],
            [2, 'review', '2026-03-10 23:30:00', false, null],
            [3, 'ship', '2026-03-12 12:00:00', true, ''],
        ] as [$id, $name, $at, $active, $note]) {
            $this->em->persist(new CountEvent($id, $name, new \DateTimeImmutable($at), $active, $note));
        }
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('customerSearches')]
    public function it_filters_customers(array $payload, array $expected): void
    {
        $columns = [NumberColumn::new('id', 'Id'), TextColumn::new('name', 'Name')];

        $this->assertSame($expected, $this->names(CountCustomer::class, $columns, $payload, 'name'));
    }

    public static function customerSearches(): iterable
    {
        $c = static fn (string $col, string $cond, array $v): array => ['origData' => $col, 'condition' => $cond, 'type' => 'string', 'value' => $v];

        yield 'starts' => [['criteria' => [$c('name', 'starts', ['al'])]], ['Alice', 'Alina']];
        yield 'not contains' => [['criteria' => [$c('name', '!contains', ['l'])]], ['Bob']];
        yield 'ends' => [['criteria' => [$c('name', 'ends', ['b'])]], ['Bob']];
        yield 'number greater' => [['criteria' => [$c('id', '>', ['2'])]], ['Alina', 'Carl']];
        yield 'not equal' => [['criteria' => [$c('id', '!=', ['2'])]], ['Alice', 'Alina', 'Carl']];
        yield 'between' => [['criteria' => [$c('id', 'between', ['2', '3'])]], ['Bob', 'Alina']];
        yield 'reversed between' => [['criteria' => [$c('id', 'between', ['3', '2'])]], ['Bob', 'Alina']];
        yield 'not between' => [['criteria' => [$c('id', '!between', ['2', '3'])]], ['Alice', 'Carl']];
        yield 'or group' => [['logic' => 'OR', 'criteria' => [$c('id', '=', ['1']), $c('name', '=', ['Carl'])]], ['Alice', 'Carl']];
        yield 'nested' => [['criteria' => [$c('name', 'starts', ['al']), ['logic' => 'OR', 'criteria' => [$c('id', '=', ['1']), $c('id', '=', ['9'])]]]], ['Alice']];
        yield 'not null' => [['criteria' => [$c('name', '!null', [])]], self::ALL];
        yield 'null' => [['criteria' => [$c('name', 'null', [])]], []];
        yield 'unknown column discards the whole tree' => [['criteria' => [$c('id', '=', ['1']), $c('secret', '=', ['x'])]], self::ALL];
        yield 'unknown column in an or group discards the whole tree' => [['logic' => 'OR', 'criteria' => [$c('id', '=', ['1']), ['logic' => 'AND', 'criteria' => [$c('name', '=', ['Bob']), $c('secret', '=', ['x'])]]]], self::ALL];
        yield 'garbage number matches nothing' => [['criteria' => [$c('id', '=', ['abc'])]], []];
        yield 'fractional number on an integer matches nothing' => [['criteria' => [$c('id', '>', ['2.5'])]], []];
        yield 'like wildcard is literal' => [['criteria' => [$c('name', 'contains', ['%'])]], []];
        yield 'text condition on a number matches nothing' => [['criteria' => [$c('id', 'contains', ['1'])]], []];
    }

    #[Test]
    public function it_ignores_a_non_searchable_column(): void
    {
        $columns = [NumberColumn::new('id', 'Id'), TextColumn::new('name', 'Name')->setSearchable(false)];
        $payload = ['criteria' => [['origData' => 'name', 'condition' => '=', 'value' => ['Bob']]]];

        $this->assertSame(self::ALL, $this->names(CountCustomer::class, $columns, $payload, 'name'));
    }

    #[Test]
    public function it_honors_a_column_that_opts_out_of_normalized_search(): void
    {
        $column  = TextColumn::new('name', 'Name')->setSearchNormalization(false);
        $payload = ['criteria' => [['origData' => 'name', 'condition' => 'contains', 'value' => ['ALI']]]];
        $qb      = $this->query(CountCustomer::class, [$column], $payload);

        $this->assertStringNotContainsString('LOWER(', $qb->getDQL());
        $this->assertSame(['Alice', 'Alina'], array_map(static fn (CountCustomer $c): string => $c->name, $qb->getQuery()->getResult()));
    }

    /**
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('eventSearches')]
    public function it_filters_events_by_date_boolean_and_nullable_text(array $payload, array $expected): void
    {
        $columns = [
            NumberColumn::new('id', 'Id'),
            TextColumn::new('name', 'Name'),
            DateColumn::new('at', 'At'),
            BooleanColumn::new('active', 'Active'),
            TextColumn::new('note', 'Note'),
        ];

        $this->assertSame($expected, $this->names(CountEvent::class, $columns, $payload, 'name'));
    }

    public static function eventSearches(): iterable
    {
        $c = static fn (string $col, string $cond, array $v): array => ['origData' => $col, 'condition' => $cond, 'type' => 'date', 'value' => $v];

        yield 'date equals matches the whole day' => [['criteria' => [$c('at', '=', ['2026-03-10'])]], ['launch', 'review']];
        yield 'date not equal keeps other days' => [['criteria' => [$c('at', '!=', ['2026-03-10'])]], ['ship']];
        yield 'date after excludes the day itself' => [['criteria' => [$c('at', '>', ['2026-03-10'])]], ['ship']];
        yield 'date before excludes the day itself' => [['criteria' => [$c('at', '<', ['2026-03-12'])]], ['launch', 'review']];
        yield 'date on or after' => [['criteria' => [$c('at', '>=', ['2026-03-10'])]], ['launch', 'review', 'ship']];
        yield 'date on or before includes the day' => [['criteria' => [$c('at', '<=', ['2026-03-10'])]], ['launch', 'review']];
        yield 'date between includes the last day' => [['criteria' => [$c('at', 'between', ['2026-03-10', '2026-03-10'])]], ['launch', 'review']];
        yield 'date not between' => [['criteria' => [$c('at', '!between', ['2026-03-10', '2026-03-11'])]], ['ship']];
        yield 'garbage date matches nothing' => [['criteria' => [$c('at', '=', ['not a date'])]], []];
        yield 'boolean true' => [['criteria' => [$c('active', '=', ['1'])]], ['launch', 'ship']];
        yield 'boolean false' => [['criteria' => [$c('active', '=', ['0'])]], ['review']];
        yield 'null includes blank strings' => [['criteria' => [$c('note', 'null', [])]], ['review', 'ship']];
        yield 'not null' => [['criteria' => [$c('note', '!null', [])]], ['launch']];
        yield 'negated text keeps null rows' => [['criteria' => [$c('note', '!contains', ['x'])]], ['review', 'ship']];
        yield 'negated starts keeps null rows' => [['criteria' => [$c('note', '!starts', ['x'])]], ['review', 'ship']];
    }

    #[Test]
    public function it_filters_on_a_related_field(): void
    {
        $customer = $this->em->find(CountCustomer::class, 2);
        $tag      = new CountTag(1, 'vip');
        $customer->addTag($tag);
        $this->em->flush();
        $this->em->clear();

        $columns = [TextColumn::new('label', 'Label'), TextColumn::new('customer', 'Customer')->setField('customer.name')];
        $payload = ['criteria' => [['origData' => 'customer', 'condition' => '=', 'value' => ['Bob']]]];

        $this->assertSame(['vip'], $this->names(CountTag::class, $columns, $payload, 'label'));
    }

    #[Test]
    public function it_never_widens_a_scope_added_by_customize_query_builder(): void
    {
        $columns = [NumberColumn::new('id', 'Id'), TextColumn::new('name', 'Name')];
        $payload = ['logic' => 'OR', 'criteria' => [
            ['origData' => 'name', 'condition' => '=', 'value' => ['Carl']],
            ['origData' => 'name', 'condition' => '=', 'value' => ['Bob']],
        ]];
        $scope = static fn (QueryBuilder $qb) => $qb->andWhere($qb->expr()->orX('e.id = 1', 'e.id = 2'));

        $this->assertSame(['Bob'], $this->names(CountCustomer::class, $columns, $payload, 'name', $scope));
    }

    /**
     * @param list<ColumnInterface> $columns
     *
     * @return list<string>
     */
    private function names(string $class, array $columns, array $payload, string $property, ?\Closure $scope = null): array
    {
        $rows = $this->query($class, $columns, $payload, $scope)->getQuery()->getResult();

        return array_map(static fn (object $row): string => $row->{$property}, $rows);
    }

    /**
     * @param list<ColumnInterface> $columns
     */
    private function query(string $class, array $columns, array $payload, ?\Closure $scope = null): QueryBuilder
    {
        $request = new DataTableRequest(1, new Columns([]), searchBuilder: SearchBuilderCriteria::parse($payload));
        $qb      = $this->em->createQueryBuilder()->select('e')->from($class, 'e')->orderBy('e.id');

        if (null !== $scope) {
            $scope($qb);
        }

        (new SearchBuilderFilter())->apply($qb, $this->context($request, $columns));

        return $qb;
    }
}
