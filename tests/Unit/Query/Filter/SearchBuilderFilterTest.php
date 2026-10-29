<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\SearchBuilderCriteria;
use Pentiminax\UX\DataTables\Query\Filter\SearchBuilderFilter;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
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

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class);

        foreach ([[1, 'Alice'], [2, 'Bob'], [3, 'Alina'], [4, 'Carl']] as [$id, $name]) {
            $this->em->persist(new CountCustomer($id, $name));
        }
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('searches')]
    public function it_filters_rows(array $payload, array $expected): void
    {
        $columns = [NumberColumn::new('id', 'Id'), TextColumn::new('name', 'Name')];
        $request = new DataTableRequest(1, new Columns([]), searchBuilder: SearchBuilderCriteria::parse($payload));
        $qb      = $this->em->createQueryBuilder()->select('e')->from(CountCustomer::class, 'e')->orderBy('e.id');

        (new SearchBuilderFilter())->apply($qb, $this->context($request, $columns));

        $names = array_map(static fn (CountCustomer $c): string => $c->name, $qb->getQuery()->getResult());

        $this->assertSame($expected, $names);
    }

    public static function searches(): iterable
    {
        $c = static fn (string $col, string $cond, array $v): array => ['origData' => $col, 'condition' => $cond, 'type' => 'string', 'value' => $v];

        yield 'starts' => [['criteria' => [$c('name', 'starts', ['al'])]], ['Alice', 'Alina']];
        yield 'not contains' => [['criteria' => [$c('name', '!contains', ['l'])]], ['Bob']];
        yield 'ends' => [['criteria' => [$c('name', 'ends', ['b'])]], ['Bob']];
        yield 'number greater' => [['criteria' => [$c('id', '>', ['2'])]], ['Alina', 'Carl']];
        yield 'between' => [['criteria' => [$c('id', 'between', ['2', '3'])]], ['Bob', 'Alina']];
        yield 'or group' => [['logic' => 'OR', 'criteria' => [$c('id', '=', ['1']), $c('name', '=', ['Carl'])]], ['Alice', 'Carl']];
        yield 'nested' => [['criteria' => [$c('name', 'starts', ['al']), ['logic' => 'OR', 'criteria' => [$c('id', '=', ['1']), $c('id', '=', ['9'])]]]], ['Alice']];
        yield 'not null' => [['criteria' => [$c('name', '!null', [])]], ['Alice', 'Bob', 'Alina', 'Carl']];
        yield 'unknown column is ignored' => [['criteria' => [$c('secret', '=', ['x'])]], ['Alice', 'Bob', 'Alina', 'Carl']];
        yield 'garbage number is ignored' => [['criteria' => [$c('id', '=', ['abc'])]], ['Alice', 'Bob', 'Alina', 'Carl']];
        yield 'like wildcard is literal' => [['criteria' => [$c('name', 'contains', ['%'])]], []];
    }
}
